<?php

declare(strict_types=1);

/**
 * Declarative desired-state for the three SPAs. Two consumers share it:
 *  - public/install.php injects it (as JSON) into the browser provisioning page,
 *    which CREATES types/fields/stages via BX24.callMethod in the admin's session
 *    (the only context Bitrix lets you create SPA user fields from — see README).
 *  - bin/provision.php --discover reads the portal back and maps schema key ->
 *    real REST field code by title, writing config/generated.<env>.php.
 *
 * Field types: all string/money/integer/double/text — no enumeration in this pass.
 * Enum values are opaque per-field ids (can't be matched across entities or set to
 * literals), and string keeps the join/logic fields simple. Dropdowns for
 * category/currency/cost_centre can be layered on later without touching logic.
 */

return [
    'request' => [
        'title'  => 'Capex Request',
        // semantic key => stage. NEW/PREPARATION/CLIENT/SUCCESS/FAIL are Bitrix
        // defaults (create=false, listed for the id mapping only). UC_* are ours.
        // STATUS_ID must be <= 18 chars.
        'stages' => [
            'draft'          => ['status' => 'NEW',         'name' => 'Draft',          'create' => false],
            'submitted'      => ['status' => 'PREPARATION', 'name' => 'Submitted',      'create' => false],
            'hod_review'     => ['status' => 'CLIENT',      'name' => 'HOD review',     'create' => false],
            'finance_review' => ['status' => 'UC_FIN',      'name' => 'Finance review', 'sort' => 35, 'create' => true],
            'approved'       => ['status' => 'SUCCESS',     'name' => 'Approved',       'create' => false],
            'closed'         => ['status' => 'UC_CLOSED',   'name' => 'Closed',         'sort' => 45, 'create' => true],
            'rejected'       => ['status' => 'FAIL',        'name' => 'Rejected',       'create' => false],
        ],
        'fields' => [
            'req_code'          => ['title' => 'Request code',      'type' => 'string'],
            'region'            => ['title' => 'Region',            'type' => 'string'], // join key
            'cost_centre'       => ['title' => 'Cost centre',       'type' => 'string'],
            'category'          => ['title' => 'Category',          'type' => 'string'],
            'amount_local'      => ['title' => 'Amount (local)',    'type' => 'money'],
            'currency'          => ['title' => 'Currency',          'type' => 'string'],
            'amount_sgd'        => ['title' => 'Amount (SGD)',      'type' => 'money'],   // app-written
            'justification'     => ['title' => 'Justification',     'type' => 'text'],
            'payback_months'    => ['title' => 'Payback (months)',  'type' => 'integer'],
            'envelope_id'       => ['title' => 'Envelope id',       'type' => 'integer'], // app-written
            'budget_verdict'    => ['title' => 'Budget verdict',    'type' => 'string'],  // app-written
            'over_by_sgd'       => ['title' => 'Over by (SGD)',     'type' => 'money'],   // app-written
            'reallocation_note' => ['title' => 'Reallocation note', 'type' => 'text'],
            'gl_code'           => ['title' => 'GL code',           'type' => 'string'],
        ],
    ],

    'envelope' => [
        'title'  => 'Budget Envelope',
        'stages' => [],
        'fields' => [
            'region'         => ['title' => 'Region',         'type' => 'string'], // join key
            'fy'             => ['title' => 'Fiscal year',    'type' => 'integer'],
            'approved_sgd'   => ['title' => 'Approved (SGD)', 'type' => 'money'],
            'committed_sgd'  => ['title' => 'Committed (SGD)','type' => 'money'],  // app-written
            'spent_sgd'      => ['title' => 'Spent (SGD)',    'type' => 'money'],  // app-written
            'fx_rate_to_sgd' => ['title' => 'FX rate to SGD', 'type' => 'double'],
            'status'         => ['title' => 'Status',         'type' => 'string'], // draft/locked
        ],
    ],

    'target' => [
        'title'  => 'Sales Target',
        'stages' => [],
        'fields' => [
            'region'     => ['title' => 'Region',       'type' => 'string'],
            'period'     => ['title' => 'Period',       'type' => 'string'],
            'target_sgd' => ['title' => 'Target (SGD)', 'type' => 'money'],
            'actual_sgd' => ['title' => 'Actual (SGD)', 'type' => 'money'],
        ],
    ],
];
