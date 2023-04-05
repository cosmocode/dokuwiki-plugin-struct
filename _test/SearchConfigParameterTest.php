<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;
use DOMWrap\Document;

/**
 * Tests handling dynamic search parameters
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class SearchConfigParameterTest extends StructTest
{

    public function setUp(): void
    {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
        $this->loadSchemaJSON('schema2');

        $as = mock\Assignments::getInstance();

        $as->assignPageSchema('page01', 'schema1');
        $this->saveData(
            'page01',
            'schema1',
            [
                'first' => 'first data',
                'second' => ['second data', 'more data', 'even more'],
                'third' => 'third data',
                'fourth' => 'fourth data'
            ],
            time()
        );

        $as->assignPageSchema('page01', 'schema2');
        $this->saveData(
            'page01',
            'schema2',
            [
                'afirst' => 'first data',
                'asecond' => ['second data', 'more data', 'even more'],
                'athird' => 'third data',
                'afourth' => 'fourth data'
            ],
            time()
        );

        for ($i = 10; $i <= 20; $i++) {
            $as->assignPageSchema("page$i", 'schema2');
            $this->saveData(
                "page$i",
                'schema2',
                [
                    'afirst' => "page$i first data",
                    'asecond' => ["page$i second data"],
                    'athird' => "page$i third data",
                    'afourth' => "page$i fourth data"
                ],
                time()
            );
        }
    }

    public function test_constructor()
    {
        global $INPUT;

        $data = [
            'schemas' => [
                ['schema1', 'alias1'],
                ['schema2', 'alias2'],
            ],
            'cols' => [
                '%pageid%',
                'first', 'second', 'third', 'fourth',
                'afirst', 'asecond', 'athird', 'afourth',
            ]
        ];

        // init with no parameters
        $expect = $data;
        $params = [];
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());

        // init with sort
        $INPUT->set(meta\SearchConfigParameters::$PARAM_SORT, '^alias2.athird');
        $expect['sort'] = [['schema2.athird', false]];
        $params[meta\SearchConfigParameters::$PARAM_SORT] = '^schema2.athird';
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());

        // init with offset
        $INPUT->set(meta\SearchConfigParameters::$PARAM_OFFSET, 25);
        $expect['offset'] = 25;
        $params[meta\SearchConfigParameters::$PARAM_OFFSET] = 25;
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());

        // init with filters
        $_REQUEST[meta\SearchConfigParameters::$PARAM_FILTER]['alias1.first*~'] = 'test';
        $_REQUEST[meta\SearchConfigParameters::$PARAM_FILTER]['afirst='] = 'test2';
        $expect['filter'] = [
            ['schema1.first', '*~', 'test', 'AND'],
            ['schema2.afirst', '=', 'test2', 'AND']
        ];
        $params[meta\SearchConfigParameters::$PARAM_FILTER . '[schema1.first*~]'] = 'test';
        $params[meta\SearchConfigParameters::$PARAM_FILTER . '[schema2.afirst=]'] = 'test2';
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());
    }

    public function test_filter()
    {
        $data = [
            'schemas' => [
                ['schema1', 'alias1'],
                ['schema2', 'alias2'],
            ],
            'cols' => [
                '%pageid%',
                'first', 'second', 'third', 'fourth',
                'afirst', 'asecond', 'athird', 'afourth',
            ]
        ];

        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $expect = [];
        $this->assertEquals($expect, $dynamic->getFilters());

        $dynamic->addFilter('first', '*~', 'test');
        $expect = ['schema1.first' => ['*~', 'test']];
        $this->assertEquals($expect, $dynamic->getFilters());

        $dynamic->addFilter('asecond', '*~', 'test2');
        $expect = ['schema1.first' => ['*~', 'test'], 'schema2.asecond' => ['*~', 'test2']];
        $this->assertEquals($expect, $dynamic->getFilters());

        // overwrite a filter
        $dynamic->addFilter('asecond', '*~', 'foobar');
        $expect = ['schema1.first' => ['*~', 'test'], 'schema2.asecond' => ['*~', 'foobar']];
        $this->assertEquals($expect, $dynamic->getFilters());

        // overwrite a filter with blank removes
        $dynamic->addFilter('asecond', '*~', '');
        $expect = ['schema1.first' => ['*~', 'test']];
        $this->assertEquals($expect, $dynamic->getFilters());

        // adding unknown filter does nothing
        $dynamic->addFilter('nope', '*~', 'foobar');
        $expect = ['schema1.first' => ['*~', 'test']];
        $this->assertEquals($expect, $dynamic->getFilters());

        // removing unknown column does nothing
        $dynamic->removeFilter('nope');
        $expect = ['schema1.first' => ['*~', 'test']];
        $this->assertEquals($expect, $dynamic->getFilters());

        $dynamic->removeFilter('first');
        $expect = [];
        $this->assertEquals($expect, $dynamic->getFilters());
    }

    public function test_sort()
    {
        $data = [
            'schemas' => [
                ['schema1', 'alias1'],
                ['schema2', 'alias2'],
            ],
            'cols' => [
                '%pageid%',
                'first', 'second', 'third', 'fourth',
                'afirst', 'asecond', 'athird', 'afourth',
            ]
        ];

        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();

        $dynamic->setSort('%pageid%');
        $conf = $dynamic->updateConfig($data);
        $param = $dynamic->getURLParameters();
        $this->assertEquals([['%pageid%', true]], $conf['sort']);
        $this->assertArrayHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
        $this->assertEquals('%pageid%', $param[meta\SearchConfigParameters::$PARAM_SORT]);

        $dynamic->setSort('%pageid%', false);
        $conf = $dynamic->updateConfig($data);
        $param = $dynamic->getURLParameters();
        $this->assertEquals([['%pageid%', false]], $conf['sort']);
        $this->assertArrayHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
        $this->assertEquals('^%pageid%', $param[meta\SearchConfigParameters::$PARAM_SORT]);

        $dynamic->removeSort();
        $conf = $dynamic->updateConfig($data);
        $param = $dynamic->getURLParameters();
        $this->assertArrayNotHasKey('sort', $conf);
        $this->assertArrayNotHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
    }

    public function test_pagination()
    {
        global $INPUT;

        $data = [
            'schemas' => [
                ['schema2', 'alias2'],
            ],
            'cols' => [
                'afirst'
            ],
            'rownumbers' => '1',
            'limit' => '5',
        ];

        $R = new \Doku_Renderer_xhtml();
        // init with offset
        $INPUT->set(meta\SearchConfigParameters::$PARAM_OFFSET, 5);
        $searchConfig = new meta\SearchConfig($data);
        $aggregationTable = new meta\AggregationTable('test_pagination', 'xhtml', $R, $searchConfig);
        $aggregationTable->render();

        $rev = time();

        $doc = new Document();
        $doc->html($R->doc);
        $table = $doc->find('div.structaggregation');

        $tr1 = $table->find(".row1");
        $this->assertEquals('6page14 first data', trim($tr1->text()));
        $this->assertEquals('page14', $tr1->attr('data-pid'));
        $this->assertEquals('0', $tr1->attr('data-rid'));
        $this->assertEquals($rev, $tr1->attr('data-rev'));

        $tr6aPrev = $table->find(".row6 a.prev");
        $this->assertEquals('/./doku.php?id=test_pagination', $tr6aPrev->attr('href'));

    }
}
