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
    /** @var int fixed revision timestamp */
    protected $fixedrev;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
        $this->loadSchemaJSON('schema2');

        $as = mock\Assignments::getInstance();

        // save all data with the same fake revision
        $this->fixedrev = time();

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
            $this->fixedrev
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
            $this->fixedrev
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
                $this->fixedrev
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
        $params = [];
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($data, $searchConfig->getConf(), 'config as is');
        $this->assertEquals($params, $dynamic->getURLParameters(), 'no dynamic parameters');

        // init with sort
        $INPUT->set(meta\SearchConfigParameters::$PARAM_SORT, '^alias2.athird');
        $params[meta\SearchConfigParameters::$PARAM_SORT] = '^schema2.athird';
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($params, $dynamic->getURLParameters());
        $sorts = $searchConfig->getSorts();
        $this->assertArrayHasKey('schema2.athird', $sorts);
        $this->assertInstanceOf(meta\Column::class, $sorts['schema2.athird'][0]);
        $this->assertEquals('schema2.athird', $sorts['schema2.athird'][0]->getFullQualifiedLabel());
        $this->assertFalse($sorts['schema2.athird'][1], 'DESC sorting');
        $this->assertTrue($sorts['schema2.athird'][2], 'case-insensitive sorting');

        // init with offset
        $INPUT->set(meta\SearchConfigParameters::$PARAM_OFFSET, 25);
        $params[meta\SearchConfigParameters::$PARAM_OFFSET] = 25;
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($params, $dynamic->getURLParameters());
        $this->assertEquals(25, $searchConfig->getOffset(), 'offset set');

        // init with filters
        $_REQUEST[meta\SearchConfigParameters::$PARAM_FILTER]['alias1.first*~'] = 'test';
        $_REQUEST[meta\SearchConfigParameters::$PARAM_FILTER]['afirst='] = 'test2';
        $params[meta\SearchConfigParameters::$PARAM_FILTER . '[schema1.first*~]'] = 'test';
        $params[meta\SearchConfigParameters::$PARAM_FILTER . '[schema2.afirst=]'] = 'test2';
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($params, $dynamic->getURLParameters());
        $filters = $this->getInaccessibleProperty($searchConfig, 'dynamicFilter');

        $this->assertInstanceOf(meta\Column::class, $filters[0][0]);
        $this->assertEquals('schema1.first', $filters[0][0]->getFullQualifiedLabel(), 'full qualified column name');
        $this->assertEquals('%test%', $filters[0][1], 'value with like placeholders');
        $this->assertEquals('LIKE', $filters[0][2], 'comparator');
        $this->assertEquals('AND', $filters[0][3], 'boolean operator');

        $this->assertInstanceOf(meta\Column::class, $filters[1][0]);
        $this->assertEquals('schema2.afirst', $filters[1][0]->getFullQualifiedLabel(), 'full qualified column name');
        $this->assertEquals('test2', $filters[1][1], 'value with no placeholders');
        $this->assertEquals('=', $filters[1][2], 'comparator');
        $this->assertEquals('AND', $filters[1][3], 'boolean operator');
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
        $param = $dynamic->getURLParameters();
        $this->assertArrayHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
        $this->assertEquals('%pageid%', $param[meta\SearchConfigParameters::$PARAM_SORT]);

        $dynamic->setSort('%pageid%', false);
        $param = $dynamic->getURLParameters();
        $this->assertArrayHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
        $this->assertEquals('^%pageid%', $param[meta\SearchConfigParameters::$PARAM_SORT]);

        $dynamic->removeSort();
        $param = $dynamic->getURLParameters();
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
            'nesting' => 0,
            'index' => 0,
            'classes' => [],
        ];

        $R = new \Doku_Renderer_xhtml();
        // init with offset
        $INPUT->set(meta\SearchConfigParameters::$PARAM_OFFSET, 5);
        $searchConfig = new meta\SearchConfig($data);
        $aggregationTable = new meta\AggregationTable('test_pagination', 'xhtml', $R, $searchConfig);
        $aggregationTable->startScope();
        $aggregationTable->render();
        $aggregationTable->finishScope();

        $doc = new Document();
        $doc->html($R->doc);
        $table = $doc->find('div.structaggregation');

        $tr1 = $table->find(".row1");
        $this->assertEquals('6page14 first data', trim($tr1->text()));
        $this->assertEquals('page14', $tr1->attr('data-pid'));
        $this->assertEquals('0', $tr1->attr('data-rid'));
        $this->assertEquals($this->fixedrev, $tr1->attr('data-rev'));

        $tr6aPrev = $table->find(".row6 a.prev");
        $this->assertEquals(DOKU_BASE . DOKU_SCRIPT . '?id=test_pagination', $tr6aPrev->attr('href'));

    }
}
