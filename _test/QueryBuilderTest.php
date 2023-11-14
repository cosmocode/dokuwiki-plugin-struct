<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\test\mock\QueryBuilder;

/**
 * @group plugin_struct
 * @group plugins
 */
class QueryBuilderTest extends StructTest
{

    public function test_join()
    {
        $qb = new QueryBuilder();

        $qb->addTable('first');
        $qb->addTable('second');
        $qb->addTable('third');

        $qb->addLeftJoin('second', 'fourth', 'fourth', 'second.foo=fourth.foo');
        $this->assertEquals(['first', 'second', 'fourth', 'third'], array_keys($qb->from));
    }
}
