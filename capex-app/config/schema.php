<?php

declare(strict_types=1);

/**
 * Declarative desired-state for the SPAs. Two consumers share it:
 *  - public/install.php injects it (as JSON) into the browser provisioning page,
 *    which CREATES types/fields/stages via BX24.callMethod in the admin's session.
 *  - bin/provision.php --discover reads the portal back and maps schema key ->
 *    real REST field code by title, writing config/generated.<env>.php.
 *
 * Two record types: Capex Request and Sales Target. There is no budget tracking.
 */

return [
    'request' => [
        'title'  => 'Capex Request',
        // NEW/PREPARATION/CLIENT/SUCCESS/FAIL are Bitrix defaults; UC_APPROVED is ours.
        'stages' => [
            'draft'     => ['status' => 'NEW',         'name' => 'Draft',     'create' => false],
            'submitted' => ['status' => 'PREPARATION', 'name' => 'Submitted', 'create' => false],
            'approved'  => ['status' => 'UC_APPROVED', 'name' => 'Approved',  'sort' => 35, 'create' => true],
            'rejected'  => ['status' => 'FAIL',        'name' => 'Rejected',  'create' => false],
        ],
        'fields' => [
            'req_code'       => ['title' => 'Request code',     'type' => 'string'],
            'region'         => ['title' => 'Region',           'type' => 'string'],
            'cost_centre'    => ['title' => 'Cost centre',      'type' => 'string'],
            'category'       => ['title' => 'Category',         'type' => 'string'],
            'amount_local'   => ['title' => 'Amount (local)',   'type' => 'money'],
            'currency'       => ['title' => 'Currency',         'type' => 'string'],
            'amount_sgd'     => ['title' => 'Amount (SGD)',     'type' => 'money'],   // app-written
            'justification'  => ['title' => 'Justification',    'type' => 'text'],
            'payback_months' => ['title' => 'Payback (months)', 'type' => 'integer'],
            'pic'            => ['title' => 'PIC',              'type' => 'string'],
            'timeline'       => ['title' => 'Timeline',         'type' => 'string'],
            'date_request'   => ['title' => 'Date of request',  'type' => 'date'],    // app-written
            'date_approval'  => ['title' => 'Date of approval', 'type' => 'date'],    // app-written
        ],
    ],

    'target' => [
        'title'  => 'Sales Target',
        'stages' => [],
        'fields' => [
            // Titles must match the existing Bitrix field titles for discovery.
            // UI labels ("New target" / "Current met") live in the views, not here.
            'region'     => ['title' => 'Region',       'type' => 'string'],
            'period'     => ['title' => 'Period',       'type' => 'string'],
            'target_sgd' => ['title' => 'Target (SGD)', 'type' => 'money'],  // shown as "New target"
            'actual_sgd' => ['title' => 'Actual (SGD)', 'type' => 'money'],  // shown as "Current met"
        ],
    ],
];
