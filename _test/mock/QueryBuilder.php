<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;

class QueryBuilder extends meta\QueryBuilder
{
    public $from;

    /**
     * for debugging where statements
     *
     * @return array ($sql, $opts)
     */
    public function getWhereSQL()
    {
        return [$this->filters()->toSQL(), array_values($this->values)];
    }
}
