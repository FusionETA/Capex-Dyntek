<?php

declare(strict_types=1);

/**
 * Placement registration on first install.
 *
 * Bitrix24 posts the initial auth bundle here. Persist the tokens, then register
 * the left-menu / CRM_DYNAMIC_<id>_LIST_MENU placements for the three screens.
 *
 * TODO(M2): implement token persistence (Auth::store) and placement.bind calls.
 */

require __DIR__ . '/../src/Autoload.php';

$config = require __DIR__ . '/../config/app.php';

// 1. Capex\Bitrix\Auth::store($_REQUEST['AUTH_ID'], $_REQUEST['REFRESH_ID'], ...)
// 2. placement.bind for Dashboard / Budget / Targets
// 3. register the onCrmDynamicItemUpdate event -> /webhook

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><p>Capex app installed.</p>';
