<?php

declare(strict_types=1);

/**
 * Declarative desired-state for the three SPAs. The provisioner reads this,
 * creates anything missing in the portal, then discovers the real REST field
 * codes and writes config/generated.php. Editing a title/type/option here and
 * re-running provision is the supported way to evolve the schema.
 *
 * Field-type notes (deliberate, see Provisioner + build plan §3):
 *  - Join keys and app-written fields are `string`, NOT `enumeration`. Bitrix
 *    stores enum values as opaque per-field ids, so an enum "MY" on a Request
 *    and an enum "MY" on an Envelope have DIFFERENT ids and can't be matched,
 *    and writing the literal "WITHIN" to an enum field wouldn't stick. Region
 *    (the envelope join key) and every app-written field are therefore strings.
 *  - Purely user-selected display fields (category, currency) stay enumeration
 *    so the UI gets a dropdown; logic never branches on them.
 */

return [
    'request' => [
        'title'  => 'Capex Request',
        'stages' => [
            // semantic key => [STATUS_ID suffix, display name]. NEW/PREPARATION/
            // CLIENT/SUCCESS/FAIL are Bitrix defaults; UC_* are added by us.
            'draft'          => ['NEW',         'Draft'],
            'submitted'      => ['PREPARATION', 'Submitted'],
            'hod_review'     => ['CLIENT',      'HOD review'],
            'finance_review' => ['UC_FIN',      'Finance review'],
            'approved'       => ['SUCCESS',     'Approved'],
            'closed'         => ['UC_CLOSED',   'Closed'],
            'rejected'       => ['FAIL',        'Rejected'],
        ],
        'fields' => [
            'req_code'          => ['title' => 'Request code',        'type' => 'string'],
            'region'            => ['title' => 'Region',              'type' => 'string'], // join key
            'cost_centre'       => ['title' => 'Cost centre',         'type' => 'enumeration', 'items' => ['IT', 'Plant', 'Building', 'Vehicle', 'Other']],
            'category'          => ['title' => 'Category',            'type' => 'enumeration', 'items' => ['IT', 'Plant & machinery', 'Building', 'Vehicle', 'Other']],
            'amount_local'      => ['title' => 'Amount (local)',      'type' => 'money'],
            'currency'          => ['title' => 'Currency',            'type' => 'enumeration', 'items' => ['SGD', 'HKD', 'MYR', 'IDR']],
            'amount_sgd'        => ['title' => 'Amount (SGD)',        'type' => 'money'],    // app-written
            'justification'     => ['title' => 'Justification',       'type' => 'text'],
            'payback_months'    => ['title' => 'Payback (months)',    'type' => 'integer'],
            'envelope_id'       => ['title' => 'Envelope id',         'type' => 'integer'],  // app-written
            'budget_verdict'    => ['title' => 'Budget verdict',      'type' => 'string'],   // app-written WITHIN/OVER
            'over_by_sgd'       => ['title' => 'Over by (SGD)',       'type' => 'money'],    // app-written
            'reallocation_note' => ['title' => 'Reallocation note',   'type' => 'text'],
            'gl_code'           => ['title' => 'GL code',             'type' => 'string'],
        ],
    ],

    'envelope' => [
        'title'  => 'Budget Envelope',
        'stages' => [],
        'fields' => [
            'region'         => ['title' => 'Region',            'type' => 'string'], // join key
            'fy'             => ['title' => 'Fiscal year',       'type' => 'integer'],
            'approved_sgd'   => ['title' => 'Approved (SGD)',    'type' => 'money'],
            'committed_sgd'  => ['title' => 'Committed (SGD)',   'type' => 'money'],  // app-written
            'spent_sgd'      => ['title' => 'Spent (SGD)',       'type' => 'money'],  // app-written
            'fx_rate_to_sgd' => ['title' => 'FX rate to SGD',    'type' => 'double'],
            'status'         => ['title' => 'Status',            'type' => 'string'], // draft/locked
        ],
    ],

    'target' => [
        'title'  => 'Sales Target',
        'stages' => [],
        'fields' => [
            'region'     => ['title' => 'Region',        'type' => 'string'],
            'period'     => ['title' => 'Period',        'type' => 'string'],
            'target_sgd' => ['title' => 'Target (SGD)',  'type' => 'money'],
            'actual_sgd' => ['title' => 'Actual (SGD)',  'type' => 'money'],
        ],
    ],
];
