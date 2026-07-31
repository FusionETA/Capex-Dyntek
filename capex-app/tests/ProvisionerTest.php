<?php

declare(strict_types=1);

/**
 * Tests Provisioner::discover() — the read-only server path that runs AFTER the
 * browser (BX24) has created the SPAs. Against a fake portal simulating that
 * post-install state, it asserts:
 *   - each schema entity resolves to its entityTypeId (found by title)
 *   - every schema field key maps to the REST code Bitrix assigned (by title, not guessed)
 *   - semantic stages map to full DT{entityTypeId}_{categoryId}:{STATUS} ids
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
 * Fake portal already provisioned by the browser: three types exist, each with its
 * schema fields registered under Bitrix-style codes that are deliberately NOT the
 * schema keys — so the test proves discovery works by title.
 */
final class ProvisionedPortal implements ClientInterface
{
    /** @var array<string,int> title => entityTypeId */
    private array $typeIds = ['Capex Request' => 1292, 'Sales Target' => 1294];

    /** @var array<int,array<string,string>> entityTypeId => [restCode => title] */
    private array $fields = [];

    /** @param array<string,mixed> $schema */
    public function __construct(array $schema)
    {
        $map = ['request' => 1292, 'target' => 1294];
        foreach ($schema as $key => $spec) {
            $etid = $map[$key];
            $this->fields[$etid] = [];
            foreach ($spec['fields'] as $f) {
                $code = 'ufCrm_' . $etid . '_' . substr(md5($f['title']), 0, 6);
                $this->fields[$etid][$code] = $f['title'];
            }
        }
    }

    public function call(string $method, array $params = []): array
    {
        switch ($method) {
            case 'crm.type.list':
                $title = (string) ($params['filter']['title'] ?? '');
                return isset($this->typeIds[$title])
                    ? ['result' => ['types' => [['entityTypeId' => $this->typeIds[$title], 'id' => 5, 'title' => $title]]]]
                    : ['result' => ['types' => []]];

            case 'crm.item.fields':
                $etid = (int) $params['entityTypeId'];
                $out = [];
                foreach ($this->fields[$etid] ?? [] as $code => $title) {
                    $out[$code] = ['title' => $title];
                }
                return ['result' => ['fields' => $out]];

            case 'crm.category.list':
                return ['result' => ['categories' => [['id' => 230]]]];
        }

        return ['result' => []];
    }

    public function batch(array $commands, bool $halt = false): array
    {
        return [];
    }
}

$schema = require __DIR__ . '/../config/schema.php';
$portal = new ProvisionedPortal($schema);

$gen = (new Provisioner($portal, $schema))->discover();

// entities
check('request entity id', 1292, $gen['entities']['request']);
check('target entity id', 1294, $gen['entities']['target']);

// field discovery by title
$regionCode = $gen['fields']['request']['region'];
check('region code discovered (not the schema key)', true, $regionCode !== 'region' && $regionCode !== '');
check('every request field resolved', 0,
    count(array_filter($gen['fields']['request'], static fn ($c) => $c === '')));
check('target new-target field resolved', true, ($gen['fields']['target']['target_sgd'] ?? '') !== '');

// stages -> full DT ids on the request category (230)
check('submitted stage id', 'DT1292_230:PREPARATION', $gen['stages']['submitted']);
check('approved stage id (custom)', 'DT1292_230:UC_APPROVED', $gen['stages']['approved']);
check('rejected stage id', 'DT1292_230:FAIL', $gen['stages']['rejected']);

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
