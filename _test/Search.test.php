<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;

/**
 * Tests for the building of SQL-Queries for the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class Search_struct_test extends StructTest {

    public function setUp() {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
        $this->loadSchemaJSON('schema2');
        $_SERVER['REMOTE_USER'] = 'testuser';

        $as = mock\Assignments::getInstance();
        $page = 'page01';
        $as->assignPageSchema($page, 'schema1');
        $as->assignPageSchema($page, 'schema2');
        saveWikiText($page, "===== TestTitle =====\nabc", "Summary");
        p_get_metadata($page);
        $this->saveData(
            $page,
            'schema1',
            array(
                'first' => 'first data',
                'second' => array('second data', 'more data', 'even more'),
                'third' => 'third data',
                'fourth' => 'fourth data'
            )
        );
        $this->saveData(
            $page,
            'schema2',
            array(
                'afirst' => 'first data',
                'asecond' => array('second data', 'more data', 'even more'),
                'athird' => 'third data',
                'afourth' => 'fourth data'
            )
        );

        $as->assignPageSchema('test:document', 'schema1');
        $as->assignPageSchema('test:document', 'schema2');
        $this->saveData(
            'test:document',
            'schema1',
            array(
                'first' => 'document first data',
                'second' => array('second', 'more'),
                'third' => '',
                'fourth' => 'fourth data'
            )
        );
        $this->saveData(
            'test:document',
            'schema2',
            array(
                'afirst' => 'first data',
                'asecond' => array('second data', 'more data', 'even more'),
                'athird' => 'third data',
                'afourth' => 'fourth data'
            )
        );

        for($i = 10; $i <= 20; $i++) {
            $this->saveData(
                "page$i",
                'schema2',
                array(
                    'afirst' => "page$i first data",
                    'asecond' => array("page$i second data"),
                    'athird' => "page$i third data",
                    'afourth' => "page$i fourth data"
                )
            );
            $as->assignPageSchema("page$i", 'schema2');
        }
    }

    public function test_simple() {
        $search = new mock\Search();

        $search->addSchema('schema1');
        $search->addColumn('%pageid%');
        $search->addColumn('first');
        $search->addColumn('second');

        /** @var meta\Value[][] $result */
        $result = $search->execute();

        $this->assertEquals(2, count($result), 'result rows');
        $this->assertEquals(3, count($result[0]), 'result columns');
        $this->assertEquals('page01', $result[0][0]->getValue());
        $this->assertEquals('first data', $result[0][1]->getValue());
        $this->assertEquals(array('second data', 'more data', 'even more'), $result[0][2]->getValue());
    }

    public function test_simple_title() {
        $search = new mock\Search();

        $search->addSchema('schema1');
        $search->addColumn('%title%');
        $search->addColumn('first');
        $search->addColumn('second');

        /** @var meta\Value[][] $result */
        $result = $search->execute();

        $this->assertEquals(2, count($result), 'result rows');
        $this->assertEquals(3, count($result[0]), 'result columns');
        $this->assertEquals('["page01","TestTitle"]', $result[0][0]->getValue());
        $this->assertEquals('first data', $result[0][1]->getValue());
        $this->assertEquals(array('second data', 'more data', 'even more'), $result[0][2]->getValue());
    }

    public function test_search_lasteditor() {
        $search = new mock\Search();

        $search->addSchema('schema1');
        $search->addColumn('%title%');
        $search->addColumn('%lasteditor%');
        $search->addColumn('first');
        $search->addColumn('second');

        /** @var meta\Value[][] $result */
        $result = $search->execute();

        $this->assertEquals(2, count($result), 'result rows');
        $this->assertEquals(4, count($result[0]), 'result columns');
        $this->assertEquals('testuser', $result[0][1]->getValue());
        $this->assertEquals(array('second data', 'more data', 'even more'), $result[0][3]->getValue());
    }


    /**
     * @group slow
     */
    public function test_search_lastupdate() {
        sleep(1);
        saveWikiText('page01', "===== TestTitle =====\nabcd", "Summary");
        p_get_metadata('page01');

        $search = new mock\Search();

        $search->addSchema('schema1');
        $search->addColumn('%pageid%');
        $search->addColumn('%lastupdate%');
        $search->addColumn('first');
        $search->addColumn('second');

        /** @var meta\Value[][] $result */
        $result = $search->execute();

        $expected_time = dformat(filemtime(wikiFN('page01')), '%Y-%m-%d %H:%M:%S');

        $this->assertEquals(2, count($result), 'result rows');
        $this->assertEquals(4, count($result[0]), 'result columns');
        $this->assertEquals($expected_time, $result[0][1]->getValue());
        $this->assertEquals(array('second data', 'more data', 'even more'), $result[0][3]->getValue());
    }

    /**
     * @group slow
     */
    public function test_search_lastsummary() {
        sleep(1);
        $summary = 'Summary';
        saveWikiText('page01', "===== TestTitle =====\nabcd", $summary);
        p_get_metadata('page01');

        $search = new mock\Search();

        $search->addSchema('schema1');
        $search->addColumn('%pageid%');
        $search->addColumn('%lastsummary%');
        $search->addColumn('first');
        $search->addColumn('second');

        /** @var meta\Value[][] $result */
        $result = $search->execute();

        $this->assertEquals(2, count($result), 'result rows');
        $this->assertEquals(4, count($result[0]), 'result columns');
        $this->assertEquals($summary, $result[0][1]->getValue());
        $this->assertEquals(array('second data', 'more data', 'even more'), $result[0][3]->getValue());
    }

    public function test_search() {
        $search = new mock\Search();

        $search->addSchema('schema1');
        $search->addSchema('schema2', 'foo');
        $this->assertEquals(2, count($search->schemas));

        $search->addColumn('first');
        $this->assertEquals('schema1', $search->columns[0]->getTable());
        $this->assertEquals(1, $search->columns[0]->getColref());

        $search->addColumn('afirst');
        $this->assertEquals('schema2', $search->columns[1]->getTable());
        $this->assertEquals(1, $search->columns[1]->getColref());

        $search->addColumn('schema1.third');
        $this->assertEquals('schema1', $search->columns[2]->getTable());
        $this->assertEquals(3, $search->columns[2]->getColref());

        $search->addColumn('foo.athird');
        $this->assertEquals('schema2', $search->columns[3]->getTable());
        $this->assertEquals(3, $search->columns[3]->getColref());

        $search->addColumn('asecond');
        $this->assertEquals('schema2', $search->columns[4]->getTable());
        $this->assertEquals(2, $search->columns[4]->getColref());

        $search->addColumn('doesntexist');
        $this->assertEquals(5, count($search->columns));

        $search->addColumn('%pageid%');
        $this->assertEquals('schema1', $search->columns[5]->getTable());
        $exception = false;
        try {
            $search->columns[5]->getColref();
        } catch(meta\StructException $e) {
            $exception = true;
        }
        $this->assertTrue($exception, "Struct exception expected for accesing colref of PageColumn");

        $search->addSort('first', false);
        $this->assertEquals(1, count($search->sortby));

        $search->addFilter('%pageid%', '%ag%', '~', 'AND');
        $search->addFilter('second', '%sec%', '~', 'AND');
        $search->addFilter('first', '%rst%', '~', 'AND');

        $result = $search->execute();
        $count = $search->getCount();

        $this->assertEquals(1, $count, 'result count');
        $this->assertEquals(1, count($result), 'result rows');
        $this->assertEquals(6, count($result[0]), 'result columns');

        // sort by multi-column
        $search->addSort('second');
        $this->assertEquals(2, count($search->sortby));
        $result = $search->execute();
        $count = $search->getCount();
        $this->assertEquals(1, $count, 'result count');
        $this->assertEquals(1, count($result), 'result rows');
        $this->assertEquals(6, count($result[0]), 'result columns');

        /*
        {#debugging
            list($sql, $opts) = $search->getSQL();
            print "\n";
            print_r($sql);
            print "\n";
            print_r($opts);
            print "\n";
            #print_r($result);
        }
        */
    }

    public function test_ranges() {
        $search = new mock\Search();
        $search->addSchema('schema2');

        $search->addColumn('%pageid%');
        $search->addColumn('afirst');
        $search->addColumn('asecond');

        $search->addFilter('%pageid%', '%ag%', '~', 'AND');

        $search->addSort('%pageid%', false);

        /** @var meta\Value[][] $result */
        $result = $search->execute();
        $count = $search->getCount();

        // check result dimensions
        $this->assertEquals(12, $count, 'result count');
        $this->assertEquals(12, count($result), 'result rows');
        $this->assertEquals(3, count($result[0]), 'result columns');

        // check sorting
        $this->assertEquals('page20', $result[0][0]->getValue());
        $this->assertEquals('page19', $result[1][0]->getValue());
        $this->assertEquals('page18', $result[2][0]->getValue());

        // now add limit
        $search->setLimit(5);
        $result = $search->execute();
        $count = $search->getCount();

        // check result dimensions
        $this->assertEquals(12, $count, 'result count'); // full result set
        $this->assertEquals(5, count($result), 'result rows'); // wanted result set

        // check the values
        $this->assertEquals('page20', $result[0][0]->getValue());
        $this->assertEquals('page16', $result[4][0]->getValue());

        // now add offset
        $search->setOffset(5);
        $result = $search->execute();
        $count = $search->getCount();

        // check result dimensions
        $this->assertEquals(12, $count, 'result count'); // full result set
        $this->assertEquals(5, count($result), 'result rows'); // wanted result set

        // check the values
        $this->assertEquals('page15', $result[0][0]->getValue());
        $this->assertEquals('page11', $result[4][0]->getValue());
    }

    public static function addFilter_testdata() {
        return array(
            array('%pageid%', 'val', '<>', 'OR', array(array('%pageid%', 'val', '!=', 'OR')), false, 'replace <> comp'),
            array('%pageid%', 'val', '*~', 'OR', array(array('%pageid%', '%val%', 'LIKE', 'OR')), false, 'replace *~ comp'),
            array('%pageid%', 'val*', '~', 'OR', array(array('%pageid%', 'val%', 'LIKE', 'OR')), false, 'replace * in value'),
            array('%pageid%', 'val.*', '=*', 'OR', array(array('%pageid%', 'val.*', 'REGEXP', 'OR')), false, 'replace * in value'),
            array('nonexisting', 'val', '~', 'OR', array(), false, 'ignore missing columns'),
            array('%pageid%', 'val', '?', 'OR', array(), '\dokuwiki\plugin\struct\meta\StructException', 'wrong comperator'),
            array('%pageid%', 'val', '=', 'NOT', array(), '\dokuwiki\plugin\struct\meta\StructException', 'wrong type')
        );
    }

    /**
     * @dataProvider addFilter_testdata
     *
     */
    public function test_addFilter($colname, $value, $comp, $type, $expected_filter, $expectException, $msg) {
        $search = new mock\Search();
        $search->addSchema('schema2');
        $search->addColumn('%pageid%');
        if($expectException !== false) $this->setExpectedException($expectException);

        $search->addFilter($colname, $value, $comp, $type);

        if(count($expected_filter) === 0) {
            $this->assertEquals(count($search->filter), 0, $msg);
            return;
        }
        $this->assertEquals($expected_filter[0][0], $search->filter[0][0]->getLabel(), $msg);
        $this->assertEquals($expected_filter[0][1], $search->filter[0][1], $msg);
        $this->assertEquals($expected_filter[0][2], $search->filter[0][2], $msg);
        $this->assertEquals($expected_filter[0][3], $search->filter[0][3], $msg);
    }

    public function test_wildcard() {
        $search = new mock\Search();
        $search->addSchema('schema2', 'alias');
        $search->addColumn('*');
        $this->assertEquals(4, count($search->getColumns()));

        $search = new mock\Search();
        $search->addSchema('schema2', 'alias');
        $search->addColumn('schema2.*');
        $this->assertEquals(4, count($search->getColumns()));

        $search = new mock\Search();
        $search->addSchema('schema2', 'alias');
        $search->addColumn('alias.*');
        $this->assertEquals(4, count($search->getColumns()));

        $search = new mock\Search();
        $search->addSchema('schema2', 'alias');
        $search->addColumn('nope.*');
        $this->assertEquals(0, count($search->getColumns()));
    }

    public function test_filterValueList() {
        $search = new mock\Search();

        //simple - single quote
        $this->assertEquals(array('test'),
            $this->callInaccessibleMethod($search, 'parseFilterValueList', array('("test")')));

        //simple - double quote
        $this->assertEquals(array('test'),
            $this->callInaccessibleMethod($search, 'parseFilterValueList', array("('test')")));

        //many elements
        $this->assertEquals(array('test', 'test2', '18'),
            $this->callInaccessibleMethod($search, 'parseFilterValueList', array('("test", \'test2\', 18)')));

        $str = <<<'EOD'
("t\"est", 't\'est2', 18)
EOD;
        //escape sequences
        $this->assertEquals(array('t"est', "t'est2", '18'),
            $this->callInaccessibleMethod($search, 'parseFilterValueList', array($str)));

        //numbers
        $this->assertEquals(array('18.7', '10e5', '-100'),
            $this->callInaccessibleMethod($search, 'parseFilterValueList', array('(18.7, 10e5, -100)')));

    }
}
