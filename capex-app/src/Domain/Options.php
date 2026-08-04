<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Shared option lists for request form selects. One source of truth so the New
 * Request form, the approver's cost-centre picker, and the history editor all
 * agree. Region codes here must match config['corp_targets'] keys.
 */
final class Options
{
    public const REGIONS      = ['SG', 'HK', 'MY', 'ID'];
    public const COST_CENTRES = ['IT', 'Plant', 'Building', 'Vehicle', 'Other'];
    public const CATEGORIES   = ['IT', 'Plant & machinery', 'Building', 'Vehicle', 'Other'];
    public const CURRENCIES   = ['SGD', 'HKD', 'MYR', 'IDR'];
}
