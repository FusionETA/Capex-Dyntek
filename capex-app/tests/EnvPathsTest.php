<?php

declare(strict_types=1);

/**
 * Tests the APP_ENV resolution + per-environment path selection in App, so the
 * Fusion test portal and the Dyntek prod portal stay isolated. Pure-ish: only
 * touches a throwaway temp dir. Run: php capex-app/tests/EnvPathsTest.php
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\App;

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

// --- resolveEnv() ---
putenv('APP_ENV');            // unset
unset($_SERVER['APP_ENV']);
check('default env is prod', 'prod', App::resolveEnv());

putenv('APP_ENV=test');
check('APP_ENV=test resolves', 'test', App::resolveEnv());

putenv('APP_ENV=Prod');
check('env is lowercased', 'prod', App::resolveEnv());

// Path-traversal / junk is stripped to a safe slug.
putenv('APP_ENV=../../etc');
check('unsafe env sanitised', 'etc', App::resolveEnv());

putenv('APP_ENV=  ');
check('whitespace-only env -> prod', 'prod', App::resolveEnv());

putenv('APP_ENV'); // clean up

// --- paths(): env-specific when the config file exists ---
$dirCfg = sys_get_temp_dir() . '/capex_cfg_' . uniqid();
$dirVar = sys_get_temp_dir() . '/capex_var_' . uniqid();
mkdir($dirCfg);
mkdir($dirVar);

// No app.test.php yet -> legacy fallback names.
$legacy = App::paths($dirCfg, $dirVar, 'test');
check('legacy fallback config', "{$dirCfg}/app.php", $legacy['config']);
check('legacy fallback tokens', "{$dirVar}/tokens.sqlite", $legacy['tokens']);
check('legacy fallback generated', "{$dirCfg}/generated.php", $legacy['generated']);

// Create app.test.php -> env-specific names kick in.
file_put_contents("{$dirCfg}/app.test.php", "<?php return [];\n");
$envPaths = App::paths($dirCfg, $dirVar, 'test');
check('env-specific config', "{$dirCfg}/app.test.php", $envPaths['config']);
check('env-specific tokens', "{$dirVar}/tokens.test.sqlite", $envPaths['tokens']);
check('env-specific generated', "{$dirCfg}/generated.test.php", $envPaths['generated']);

// prod stays on legacy names when only app.test.php exists (isolation).
$prodPaths = App::paths($dirCfg, $dirVar, 'prod');
check('prod unaffected by test config', "{$dirCfg}/app.php", $prodPaths['config']);

// Two envs get distinct token stores.
file_put_contents("{$dirCfg}/app.prod.php", "<?php return [];\n");
check('test + prod use different token stores',
    true,
    App::paths($dirCfg, $dirVar, 'test')['tokens'] !== App::paths($dirCfg, $dirVar, 'prod')['tokens']);

// cleanup
@unlink("{$dirCfg}/app.test.php");
@unlink("{$dirCfg}/app.prod.php");
@rmdir($dirCfg);
@rmdir($dirVar);

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
