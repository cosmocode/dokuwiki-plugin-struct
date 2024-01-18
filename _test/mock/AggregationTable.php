<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;

class AggregationTable extends meta\AggregationTable
{
    public function getResult()
    {
        $this->executeSearch();
        return $this->result;
    }
}
