<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;

class LookupTable extends meta\LookupTable {
    public function getResult()
    {
        return $this->result;
    }
}
