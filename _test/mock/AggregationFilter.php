<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta\SearchConfig;

class AggregationFilter extends \dokuwiki\plugin\struct\meta\AggregationFilter
{
    /**
     * We do not initialize this one at all
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /** @inheritdoc */
    public function getAllColumnValues($result)
    {
        return parent::getAllColumnValues($result);
    }
}
