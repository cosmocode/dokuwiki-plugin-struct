<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;

/**
 * @group plugin_struct
 * @group plugins
 */
class QueryBuilderWhere_struct_test extends StructTest {

    public function test_sql() {
        $QB = new QueryBuilder();
        $where = new QueryBuilderWhere($QB);

        $where->whereAnd('foo = foo');
        $this->assertEquals(
            $this->cleanWS('(foo = foo)'),
            $this->cleanWS($where->toSQL())
        );

        $where->whereAnd('bar = bar');
        $this->assertEquals(
            $this->cleanWS('(foo = foo AND bar = bar)'),
            $this->cleanWS($where->toSQL())
        );

        $sub = $where->whereSubAnd();
        $this->assertEquals(
            $this->cleanWS('(foo = foo AND bar = bar)'),
            $this->cleanWS($where->toSQL())
        );

        $sub->whereAnd('zab = zab');
        $this->assertEquals(
            $this->cleanWS('(foo = foo AND bar = bar AND (zab = zab))'),
            $this->cleanWS($where->toSQL())
        );

        $sub->whereOr('fab = fab');
        $this->assertEquals(
            $this->cleanWS('(foo = foo AND bar = bar AND (zab = zab OR fab = fab))'),
            $this->cleanWS($where->toSQL())
        );
    }

    public function test_orsql() {
        $QB = new QueryBuilder();
        $where = new QueryBuilderWhere($QB);

        $where->whereAnd("foo = ''");
        $this->assertEquals(
            $this->cleanWS("(foo = '')"),
            $this->cleanWS($where->toSQL())
        );

        $sub = $where->whereSubOr();
        $sub->whereAnd('bar = bar');
        $sub->whereAnd('baz = baz');
        $this->assertEquals(
            $this->cleanWS("(foo = '' OR (bar = bar AND baz = baz))"),
            $this->cleanWS($where->toSQL())
        );
    }
}
