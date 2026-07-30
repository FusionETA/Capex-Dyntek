<?php

declare(strict_types=1);

namespace Capex\Provision;

use Capex\Bitrix\ClientInterface;

/**
 * Creates the three SPAs, their stages and their user fields from config/schema.php,
 * then discovers the REST field codes Bitrix actually assigned and returns a mapping
 * ready to write into config/generated.php.
 *
 * Idempotent by design — everything is matched by TITLE before it is created, so a
 * second run adds nothing. It never deletes: removing a field from the schema leaves
 * the portal field in place (safe default; drop it by hand if you mean to).
 *
 * IMPORTANT: the exact REST shapes for SPA creation, stage/status entities and
 * userfieldconfig vary a little across Bitrix24 versions. Run with --dry-run first,
 * then verify the generated codes against the portal. The discover-by-title step is
 * what keeps this robust even when the ufCrm_* codes aren't what you'd guess.
 */
final class Provisioner
{
    /** @var array<string,string> progress lines for the CLI */
    public array $log = [];

    /** @param array<string,mixed> $schema config/schema.php */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly array $schema,
        private readonly bool $dryRun = false,
    ) {
    }

    /**
     * Read-only discovery — the server-side path used AFTER the browser (BX24) has
     * created the types/fields/stages. Finds each type by title, maps every schema
     * field key to the REST code Bitrix assigned (matched by title), and builds the
     * semantic stage-id map. Creates nothing, so it needs no admin session.
     *
     * @return array{entities:array<string,int>,fields:array<string,array<string,string>>,stages:array<string,string>}
     */
    public function discover(): array
    {
        $entities = [];
        $fields = [];
        $stages = [];

        foreach ($this->schema as $key => $spec) {
            $entityTypeId = $this->findType((string) $spec['title']);
            if ($entityTypeId === 0) {
                $this->note("WARN: type '{$spec['title']}' not found — run the browser install first");
                continue;
            }

            $entities[$key] = $entityTypeId;
            $this->note("type '{$spec['title']}' (#{$entityTypeId})");
            $fields[$key] = $this->discoverFieldCodes($entityTypeId, $spec['fields']);

            if (!empty($spec['stages'])) {
                $stages = $this->discoverStages($entityTypeId, $spec['stages']);
            }
        }

        return ['entities' => $entities, 'fields' => $fields, 'stages' => $stages];
    }

    /** Find a dynamic type's entityTypeId by title (read-only). 0 if absent. */
    private function findType(string $title): int
    {
        $res = $this->client->call('crm.type.list', ['filter' => ['title' => $title]]);

        return (int) ($res['result']['types'][0]['entityTypeId'] ?? 0);
    }

    /**
     * Build semantic key => full stageId (e.g. 'finance_review' => 'DT1292_230:UC_FIN')
     * from the schema statuses and the type's default category.
     *
     * @param array<string,array<string,mixed>> $stages
     * @return array<string,string>
     */
    private function discoverStages(int $entityTypeId, array $stages): array
    {
        $categoryId = $this->defaultCategoryId($entityTypeId);

        $map = [];
        foreach ($stages as $semantic => $s) {
            $map[$semantic] = "DT{$entityTypeId}_{$categoryId}:{$s['status']}";
        }

        return $map;
    }

    /**
     * Apply the schema and return the discovered mapping:
     *   ['entities'=>[key=>id], 'fields'=>[key=>[fieldKey=>code]], 'stages'=>[semantic=>stageId]]
     *
     * @return array{entities:array<string,int>,fields:array<string,array<string,string>>,stages:array<string,string>}
     */
    public function apply(): array
    {
        $entities = [];
        $fields = [];
        $stages = [];

        foreach ($this->schema as $key => $spec) {
            $entityTypeId = $this->ensureType((string) $spec['title']);
            $entities[$key] = $entityTypeId;

            $this->ensureFields($entityTypeId, $spec['fields']);
            $fields[$key] = $this->discoverFieldCodes($entityTypeId, $spec['fields']);

            if (!empty($spec['stages'])) {
                $stages = $this->ensureStages($entityTypeId, $spec['stages']);
            }
        }

        return ['entities' => $entities, 'fields' => $fields, 'stages' => $stages];
    }

    /** Find a dynamic type by title, creating it if absent. Returns its entityTypeId. */
    private function ensureType(string $title): int
    {
        $existing = $this->client->call('crm.type.list', [
            'filter' => ['title' => $title],
        ]);

        $types = $existing['result']['types'] ?? [];
        if ($types !== []) {
            $id = (int) $types[0]['entityTypeId'];
            $this->note("type '{$title}' exists (#{$id})");
            return $id;
        }

        if ($this->dryRun) {
            $this->note("WOULD create type '{$title}'");
            return 0;
        }

        $created = $this->client->call('crm.type.add', [
            'fields' => [
                'title'               => $title,
                'isStagesEnabled'     => 'Y',
                'isCategoriesEnabled' => 'N',
                'isBeginCloseDatesEnabled' => 'N',
            ],
        ]);

        $id = (int) ($created['result']['type']['entityTypeId'] ?? 0);
        $this->note("created type '{$title}' (#{$id})");

        return $id;
    }

    /**
     * Ensure every schema field exists on the entity (matched by title). Adds the
     * missing ones via userfieldconfig.add.
     *
     * @param array<string,array<string,mixed>> $fields
     */
    private function ensureFields(int $entityTypeId, array $fields): void
    {
        if ($entityTypeId === 0) {
            foreach ($fields as $spec) {
                $this->note("WOULD add field '{$spec['title']}'");
            }
            return;
        }

        $existingByTitle = $this->titlesOf($entityTypeId);

        foreach ($fields as $key => $spec) {
            $title = (string) $spec['title'];
            if (isset($existingByTitle[$title])) {
                $this->note("field '{$title}' exists");
                continue;
            }

            if ($this->dryRun) {
                $this->note("WOULD add field '{$title}' ({$spec['type']})");
                continue;
            }

            $this->client->call('userfieldconfig.add', [
                'moduleId' => 'crm',
                'field'    => $this->fieldPayload($entityTypeId, $key, $spec),
            ]);
            $this->note("added field '{$title}' ({$spec['type']})");
        }
    }

    /**
     * Read the entity's live fields and map each schema key to the REST code Bitrix
     * assigned, matched by title. This is the authoritative source of ufCrm_* codes.
     *
     * @param array<string,array<string,mixed>> $fields
     * @return array<string,string>
     */
    private function discoverFieldCodes(int $entityTypeId, array $fields): array
    {
        if ($entityTypeId === 0) {
            // Dry run: return the intended keys so the report is legible.
            return array_map(static fn () => '(pending)', $fields);
        }

        $res = $this->client->call('crm.item.fields', ['entityTypeId' => $entityTypeId]);
        $live = $res['result']['fields'] ?? [];

        // Build title => REST code from the live field list.
        $codeByTitle = [];
        foreach ($live as $code => $meta) {
            $title = (string) ($meta['title'] ?? '');
            if ($title !== '') {
                $codeByTitle[$title] = $code;
            }
        }

        $mapping = [];
        foreach ($fields as $key => $spec) {
            $title = (string) $spec['title'];
            $code = $codeByTitle[$title] ?? null;
            if ($code === null) {
                $this->note("WARN: could not resolve code for '{$title}' — check the portal");
                $code = '';
            }
            $mapping[$key] = $code;
        }

        return $mapping;
    }

    /**
     * Ensure the custom stages exist and return semantic key => full stageId
     * (e.g. 'finance_review' => 'DT180_1:UC_FIN').
     *
     * @param array<string,array{0:string,1:string}> $stages
     * @return array<string,string>
     */
    private function ensureStages(int $entityTypeId, array $stages): array
    {
        if ($entityTypeId === 0) {
            return array_map(static fn ($s) => "(pending):{$s[0]}", $stages);
        }

        $categoryId = $this->defaultCategoryId($entityTypeId);
        $statusEntityId = "DYNAMIC_{$entityTypeId}_STAGE_{$categoryId}";

        $existing = $this->client->call('crm.status.list', [
            'filter' => ['ENTITY_ID' => $statusEntityId],
        ]);
        $have = [];
        foreach ($existing['result'] ?? [] as $row) {
            $have[(string) $row['STATUS_ID']] = true;
        }

        $map = [];
        foreach ($stages as $semantic => $s) {
            $statusId = "DT{$entityTypeId}_{$categoryId}:{$s['status']}";
            $map[$semantic] = $statusId;

            if (empty($s['create']) || isset($have[$statusId])) {
                continue;
            }

            if ($this->dryRun) {
                $this->note("WOULD add stage '{$s['name']}' ({$statusId})");
                continue;
            }

            $this->client->call('crm.status.add', [
                'fields' => [
                    'ENTITY_ID' => $statusEntityId,
                    'STATUS_ID' => $s['status'], // bare id; Bitrix returns it DT-prefixed
                    'NAME'      => $s['name'],
                    'SORT'      => $s['sort'] ?? 100,
                ],
            ]);
            $this->note("added stage '{$s['name']}' ({$statusId})");
        }

        return $map;
    }

    private function defaultCategoryId(int $entityTypeId): int
    {
        $res = $this->client->call('crm.category.list', ['entityTypeId' => $entityTypeId]);
        $categories = $res['result']['categories'] ?? [];

        return (int) ($categories[0]['id'] ?? 0);
    }

    /** @return array<string,string> title => REST code of existing fields */
    private function titlesOf(int $entityTypeId): array
    {
        $res = $this->client->call('crm.item.fields', ['entityTypeId' => $entityTypeId]);
        $live = $res['result']['fields'] ?? [];

        $out = [];
        foreach ($live as $code => $meta) {
            $title = (string) ($meta['title'] ?? '');
            if ($title !== '') {
                $out[$title] = $code;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $spec
     * @return array<string,mixed>
     */
    private function fieldPayload(int $entityTypeId, string $key, array $spec): array
    {
        $payload = [
            'ENTITY_ID'       => "CRM_{$entityTypeId}",
            'FIELD_NAME'      => 'UF_CRM_' . $entityTypeId . '_' . strtoupper($key),
            'USER_TYPE_ID'    => (string) $spec['type'],
            'EDIT_FORM_LABEL' => (string) $spec['title'],
            'LIST_COLUMN_LABEL' => (string) $spec['title'],
        ];

        if (($spec['type'] ?? '') === 'enumeration' && !empty($spec['items'])) {
            $list = [];
            foreach ($spec['items'] as $i => $value) {
                $list[] = ['VALUE' => (string) $value, 'SORT' => ($i + 1) * 10];
            }
            $payload['LIST'] = $list;
        }

        return $payload;
    }

    private function note(string $line): void
    {
        $this->log[] = $line;
    }
}
