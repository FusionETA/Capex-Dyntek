<?php

declare(strict_types=1);

/**
 * Exercises the provisioning orchestration against a fake Bitrix client:
 *   - missing types are created; existing types (matched by title) are reused
 *   - missing fields are added; existing fields are skipped (idempotent)
 *   - REST field codes are DISCOVERED by title, not guessed
 *   - a second apply() adds nothing
 *
 * Run: php capex-app/tests/ProvisionerTest.php
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\Bitrix\ClientInterface;
use Capex\Provision\Provisioner;

$tests = 0;
$failures = 0;

/** @param mixed $expected @param mixed $actual */
function check(string $label, $expected, $actual): void
{
    global $tests, $failures;
    $tests++;
    if ($expected === $actual) {
        echo "  ok  - {$label}\n";
    } else {
        $failures++;
        echo "  FAIL- {$label}\n";
        echo '        expected: ' . var_export($expected, true) . "\n";
        echo '        actual:   ' . var_export($actual, true) . "\n";
    }
}

/**
 * Fake portal. Types are created on demand and remember their fields; crm.item.fields
 * returns them keyed by a Bitrix-style code that is intentionally NOT the schema key,
 * so the test proves discovery-by-title works.
 */
final class FakePortal implements ClientInterface
{
    /** @var array<string,int> title => entityTypeId */
    public array $types = [];
    private int $nextTypeId = 180;

    /** @var array<int,array<string,string>> entityTypeId => [restCode => title] */
    public array $fields = [];

    /** @var array<string,int> method => call count */
    public array $calls = [];

    public function call(string $method, array $params = []): array
    {
        $this->calls[$method] = ($this->calls[$method] ?? 0) + 1;

        switch ($method) {
            case 'crm.type.list':
                $title = (string) ($params['filter']['title'] ?? '');
                if (isset($this->types[$title])) {
                    return ['result' => ['types' => [['entityTypeId' => $this->types[$title], 'title' => $title]]]];
                }
                return ['result' => ['types' => []]];

            case 'crm.type.add':
                $title = (string) $params['fields']['title'];
                $id = $this->nextTypeId++;
                $this->types[$title] = $id;
                $this->fields[$id] = [];
                return ['result' => ['type' => ['entityTypeId' => $id, 'title' => $title]]];

            case 'userfieldconfig.add':
                $entityTypeId = (int) str_replace('CRM_', '', (string) $params['field']['ENTITY_ID']);
                $title = (string) $params['field']['EDIT_FORM_LABEL'];
                // Bitrix assigns a code we can't predict from the key — fake that.
                $restCode = 'ufCrm_' . $entityTypeId . '_' . substr(md5($title), 0, 6);
                $this->fields[$entityTypeId][$restCode] = $title;
                return ['result' => 1];

            case 'crm.item.fields':
                $entityTypeId = (int) $params['entityTypeId'];
                $out = [];
                foreach ($this->fields[$entityTypeId] ?? [] as $code => $title) {
                    $out[$code] = ['title' => $title];
                }
                return ['result' => ['fields' => $out]];

            case 'crm.category.list':
                return ['result' => ['categories' => [['id' => 1]]]];

            case 'crm.status.list':
                return ['result' => []];

            case 'crm.status.add':
                return ['result' => 1];
        }

        return ['result' => []];
    }

    public function batch(array $commands, bool $halt = false): array
    {
        return [];
    }
}

$schema = require __DIR__ . '/../config/schema.php';
$portal = new FakePortal();

// --- first apply(): creates everything ---
$gen = (new Provisioner($portal, $schema))->apply();

check('created 3 types', 3, $portal->calls['crm.type.add'] ?? 0);
check('request entity id captured', 180, $gen['entities']['request']);
check('envelope entity id captured', 181, $gen['entities']['envelope']);
check('target entity id captured', 182, $gen['entities']['target']);

// discovery: schema key -> a real, non-guessed code that maps back to the right title
$regionCode = $gen['fields']['request']['region'];
check('region code discovered (not the schema key)', true, $regionCode !== 'region' && $regionCode !== '');
check('discovered code resolves to the right field',
    'Region', $portal->fields[180][$regionCode] ?? null);

// stages: semantic key -> full stageId with our type + category
check('finance_review stage id', 'DT180_1:UC_FIN', $gen['stages']['finance_review']);
check('approved stage id', 'DT180_1:SUCCESS', $gen['stages']['approved']);

// every request field resolved to a non-empty code
$unresolved = array_filter($gen['fields']['request'], static fn ($c) => $c === '');
check('all request fields resolved', 0, count($unresolved));

// --- second apply(): idempotent, adds nothing ---
$addsBefore = $portal->calls['userfieldconfig.add'] ?? 0;
$typesBefore = $portal->calls['crm.type.add'] ?? 0;
(new Provisioner($portal, $schema))->apply();

check('no new types on re-run', $typesBefore, $portal->calls['crm.type.add'] ?? 0);
check('no new fields on re-run', $addsBefore, $portal->calls['userfieldconfig.add'] ?? 0);

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
