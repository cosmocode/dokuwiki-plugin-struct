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
class AccessTableDataReplacementTest extends StructTest
{

    /** @var array alway enable the needed plugins */
    protected $pluginsEnabled = ['struct', 'sqlite'];

    public function setUp(): void
    {
        parent::setUp();
        $schemafoo = [];
        $schemafoo['new']['new1']['label'] = 'pages';
        $schemafoo['new']['new1']['ismulti'] = 1;
        $schemafoo['new']['new1']['class'] = 'Page';
        $schemafoo['new']['new1']['isenabled'] = '1';
        $schemafoo['new']['new1']['config'] = null;
        $schemafoo['new']['new1']['sort'] = 10;

        $schemabar['new']['new2']['label'] = 'data';
        $schemabar['new']['new2']['ismulti'] = 0;
        $schemabar['new']['new2']['class'] = 'Text';
        $schemabar['new']['new2']['isenabled'] = '1';
        $schemabar['new']['new2']['config'] = null;
        $schemabar['new']['new2']['sort'] = 20;

        $builder_foo = new meta\SchemaBuilder('foo', $schemafoo);
        $builder_foo->build();

        $builder_bar = new meta\SchemaBuilder('bar', $schemabar);
        $builder_bar->build();

        $as = mock\Assignments::getInstance();
        $as->assignPageSchema('start', 'foo');
        $as->assignPageSchema('no:data', 'foo');
        $as->assignPageSchema('page1', 'bar');
        $as->assignPageSchema('page2', 'bar');
        $as->assignPageSchema('page2', 'bar');

        // page data is saved with a rev timestamp
        $now = time();
        $this->saveData(
            'start',
            'foo',
            [
                'pages' => ['page1', 'page2']
            ],
            $now
        );

        $this->saveData(
            'page1',
            'bar',
            [
                'data' => 'data of page1'
            ],
            $now
        );

        $this->saveData(
            'page2',
            'bar',
            [
                'data' => 'data of page2'
            ],
            $now
        );
    }

    public function test_simple()
    {
        global $INFO;
        $INFO['id'] = 'start';
        $lines = [
            "schema    : bar",
            "cols      : %pageid%, data",
            "filter    : %pageid% = \$STRUCT.foo.pages$"
        ];

        $configParser = new meta\ConfigParser($lines);
        $actual_config = $configParser->getConfig();

        $search = new meta\SearchConfig($actual_config);
        list(, $opts) = $search->getSQL();
        $result = $search->execute();

        $this->assertEquals(['page1', 'page2'], $opts, '$STRUCT.table.col$ should not require table to be selected');
        $this->assertEquals('data of page1', $result[0][1]->getValue());
        $this->assertEquals('data of page2', $result[1][1]->getValue());
    }

    public function test_emptyfield()
    {
        global $ID;
        $ID = 'no:data';
        $lines = [
            "schema    : bar",
            "cols      : %pageid%, data",
            "filter    : %pageid% = \$STRUCT.foo.pages$"
        ];

        $configParser = new meta\ConfigParser($lines);
        $actual_config = $configParser->getConfig();

        $search = new meta\SearchConfig($actual_config);
        $result = $search->execute();

        $this->assertEquals(0, count($result), 'if no pages a given, then none should be shown');
    }

    public function dataProvider_DataFiltersAsSubQuery()
    {
        return [
            [
                [
                    "filter    : data = foo"
                ],
                "AND (data_bar.col1 = ?)",
                "The WHERE-clauses from page-syntax should be wrapped in parentheses"
            ],
            [
                [
                    "OR    : data = foo"
                ],
                "AND (data_bar.col1 = ?)",
                "A single OR clause should be treated as AND clauses"
            ],
            [
                [
                    "filter    : data = foo",
                    "OR        : data = bar"
                ],
                "AND (data_bar.col1 = ? OR data_bar.col1 = ?)",
                "The WHERE-clauses from page-syntax should be wrapped in parentheses"
            ],
            [
                [
                    "OR        : data = bar",
                    "filter    : data = foo"
                ],
                "AND (data_bar.col1 = ? AND data_bar.col1 = ?)",
                "A single OR clause should be treated as AND clauses"
            ]
        ];
    }

    /**
     * @dataProvider dataProvider_DataFiltersAsSubQuery
     */
    public function test_DataFiltersAsSubQuery($inputFilterLines, $expectedFilterWhere, $msg)
    {
        $lines = [
            "schema    : bar",
            "cols      : %pageid%, data",
        ];

        $lines = array_merge($lines, $inputFilterLines);

        $configParser = new meta\ConfigParser($lines);
        $actual_config = $configParser->getConfig();

        $search = new meta\SearchConfig($actual_config);
        list($sql,) = $search->getSQL();
        $where = array_filter(explode("\n", $sql), function ($elem) {
            return strpos($elem, 'WHERE') !== false;
        });
        $where = trim(reset($where));

        $baseWhere = "WHERE  (
                (
                    data_bar.pid = '' OR (
                        GETACCESSLEVEL(data_bar.pid) > 0
                        AND PAGEEXISTS(data_bar.pid) = 1
                        AND (
                            data_bar.rid != 0
                            OR (ASSIGNED = 1 OR ASSIGNED IS NULL)
                        )
                    )
                )
            AND (
                (IS_PUBLISHER(data_bar.pid) AND data_bar.latest = 1)
                OR (IS_PUBLISHER(data_bar.pid) !=1 AND data_bar.published = 1)
            )";

        $expected_where = $baseWhere . $expectedFilterWhere . " )";
        $this->assertEquals($this->cleanWS($expected_where), $this->cleanWS($where), $msg);
    }

}
