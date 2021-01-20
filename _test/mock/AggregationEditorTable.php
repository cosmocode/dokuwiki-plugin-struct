<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;

class AggregationEditorTable extends meta\AggregationEditorTable {
    public function getResult()
    {
        return $this->result;
    }
}
