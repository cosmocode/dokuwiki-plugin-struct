<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\AccessTablePage;
use dokuwiki\plugin\struct\meta\ConfigParser;
use dokuwiki\plugin\struct\meta\PageMeta;
use dokuwiki\plugin\struct\test\mock\AccessTable as MockAccessTableAlias;
use dokuwiki\plugin\struct\test\mock\AggregationEditorTable as MockAggregationEditorTableAlias;
use dokuwiki\plugin\struct\test\mock\AggregationTable as MockAggregationTableAlias;
use dokuwiki\plugin\struct\test\mock\SearchConfig as MockSearchConfigAlias;

/**
 * Testing serial data
 *
 * @group plugin_struct
 * @group plugins
 */
class AggregationResultsTest extends StructTest
{
    protected $sqlite;

    public function setUp(): void
    {
        parent::setUp();

        $sqlite = plugin_load('helper', 'struct_db');
        $this->sqlite = $sqlite->getDB();

        $this->loadSchemaJSON('schema1');

        $assignments = mock\Assignments::getInstance();
        $assignments->clear(true);

        // different values for each entry
        $second = [
            ['green', 'red'],
            ['green', 'blue'],
            ['blue', 'yellow']
        ];

        for ($i = 0; $i < 3; $i++) {
            // assign a schema
            $assignments->assignPageSchema("test$i", 'schema1');

            // save wiki pages
            saveWikiText("test$i", "test$i", "test$i");

            // save serial data
            $data = [
                'first' => "foo$i",
                'second' => $second[$i],
                'third' => "foobar$i",
                'fourth' => "barfoo$i",
            ];
            $accessSerial = MockAccessTableAlias::getSerialAccess('schema1', "test$i");
            $accessSerial->saveData($data);
            $accessPage = MockAccessTableAlias::getPageAccess('schema1', "test$i");
            $accessPage->saveData($data);
        }
    }

    /**
     * Test whether serial syntax produces a table of serial data limited to current page
     */
    public function test_pid()
    {
        // \syntax_plugin_struct_serial accesses the global $ID
        $id = 'test1';
        $schema = 'schema1';
        $result = $this->fetchNonPageResults($schema, $id);

        $this->assertCount(1, $result);
        $this->assertEquals('test1', $result[0][0]->getValue());
        // skip %rowid% column and test saved values
        $this->assertEquals('foo1', $result[0][2]->getValue());
        $this->assertEquals(['green', 'blue'], $result[0][3]->getValue());
        $this->assertEquals('foobar1', $result[0][4]->getValue());
        $this->assertEquals('barfoo1', $result[0][5]->getValue());
    }

    /**
     * Test simple text filter
     */
    public function test_filter_text()
    {
        $schema = 'schema1';
        $result = $this->fetchNonPageResults($schema, 'test0');
        $this->assertCount(1, $result);

        $result = $this->fetchNonPageResults($schema, 'test0', ['first', '=', 'foo0', 'AND']);
        $this->assertCount(1, $result);

        $result = $this->fetchNonPageResults($schema, 'test0', ['first', '!=', 'foo0', 'AND']);
        $this->assertCount(0, $result);
    }

    /** @noinspection PhpUnreachableStatementInspection */
    public function test_filter_multi()
    {
        $schema = 'schema1';
        $result = $this->fetchAllResults($schema, '');
        $this->assertCount(6, $result);

        $result = $this->fetchAllResults($schema, '', ['second', '=', 'green', 'AND']);
        $this->assertCount(4, $result);

        $this->markTestIncomplete('negative filters currently do not work on multi fields. See #512');

        $result = $this->fetchAllResults($schema, '', ['second', '!~', 'green', 'AND']);
        $this->assertCount(2, $result);
    }

    /**
     * Test filtering on a page field, with 'usetitles' set to true and false
     */
    public function test_filter_page()
    {
        $this->prepareLookup();
        $schema = 'pageschema';
        $result = $this->fetchNonPageResults($schema);
        $this->assertCount(3, $result);

        // 'usetitles' = true
        $result = $this->fetchNonPageResults($schema, '', ['singletitle', '*~', 'another', 'AND']);
        $this->assertCount(1, $result);

        // 'usetitles' = false
        $result = $this->fetchNonPageResults($schema, '', ['singlepage', '*~', 'this', 'AND']);
        $this->assertCount(0, $result);
    }

    /**
     * Test filtering on a DateTime field
     */
    public function test_filter_datetime()
    {
        $this->prepareDatetime();
        $schema = 'datetime';
        $result = $this->fetchNonPageResults($schema);
        $this->assertCount(3, $result);

        $result = $this->fetchNonPageResults($schema, '', ['field', '<', '2023-01-02', 'AND']);
        $this->assertCount(1, $result);

        $result = $this->fetchNonPageResults($schema, '', ['field', '<', '2023-01-01 11:00', 'AND']);
        $this->assertCount(0, $result);
    }

    /**
     * Test whether aggregation tables respect revoking of schema assignments
     */
    public function test_assignments()
    {
        $result = $this->fetchAllResults('schema1');
        $this->assertCount(6, $result);

        // revoke assignment
        $assignments = mock\Assignments::getInstance();
        $assignments->deassignPageSchema('test0', 'schema1');

        $result = $this->fetchAllResults('schema1');
        $this->assertCount(5, $result);
    }


    /**
     * Initialize a table from syntax and return the result from its internal search.
     *
     * @param string $schema
     * @param string $id
     * @param array $filters
     * @return \dokuwiki\plugin\struct\meta\Value[][]
     */
    protected function fetchAllResults($schema, $id = '', $filters = [])
    {
        $syntaxConfig = ['schema: ' . $schema, 'cols: %pageid%, %rowid%, *'];
        $configParser = new ConfigParser($syntaxConfig);
        $config = $configParser->getConfig();

        if ($filters) $config['filter'][] = $filters;
        $search = new MockSearchConfigAlias($config);

        $table = new MockAggregationTableAlias($id, 'xhtml', new \Doku_Renderer_xhtml(), $search);
        return $table->getResult();
    }

    /**
     * Initialize a lookup table from syntax and return the result from its internal search.
     *
     * @param string $schema
     * @param string $id
     * @param array $filters
     * @return \dokuwiki\plugin\struct\meta\Value[][]
     */
    protected function fetchNonPageResults($schema, $id = '', $filters = [])
    {
        $syntaxConfig = ['schema: ' . $schema, 'cols: %pageid%, %rowid%, *'];
        $configParser = new ConfigParser($syntaxConfig);
        $config = $configParser->getConfig();

        // simulate addYypeFilter() from \syntax_plugin_struct_serial and \syntax_plugin_struct_lookup
        if ($id) {
            $config['filter'][] = ['%rowid%', '!=', (string)AccessTablePage::DEFAULT_PAGE_RID, 'AND'];
            $config['filter'][] = ['%pageid%', '=', $id, 'AND'];
        } else {
            $config['filter'][] = ['%rowid%', '!=', (string)AccessTablePage::DEFAULT_PAGE_RID, 'AND'];
            $config['filter'][] = ['%pageid%', '=*', '^(?![\s\S])', 'AND'];
        }

        if ($filters) $config['filter'][] = $filters;
        $search = new MockSearchConfigAlias($config);

        $table = new MockAggregationEditorTableAlias($id, 'xhtml', new \Doku_Renderer_xhtml(), $search);
        return $table->getResult();
    }

    protected function prepareLookup()
    {
        saveWikiText('title1', 'test', 'test');
        $pageMeta = new PageMeta('title1');
        $pageMeta->setTitle('This is a title');

        saveWikiText('title2', 'test', 'test');
        $pageMeta = new PageMeta('title2');
        $pageMeta->setTitle('This is a 2nd title');

        saveWikiText('title3', 'test', 'test');
        $pageMeta = new PageMeta('title3');
        $pageMeta->setTitle('Another Title');

        $this->loadSchemaJSON('pageschema');
        $access = MockAccessTableAlias::getGlobalAccess('pageschema');
        $access->saveData(
            [
                'singlepage' => 'title1',
                'multipage' => ['title1'],
                'singletitle' => 'title1',
                'multititle' => ['title1'],
            ]
        );
        $access = MockAccessTableAlias::getGlobalAccess('pageschema');
        $access->saveData(
            [
                'singlepage' => 'title2',
                'multipage' => ['title2'],
                'singletitle' => 'title2',
                'multititle' => ['title2'],
            ]
        );
        $access = MockAccessTableAlias::getGlobalAccess('pageschema');
        $access->saveData(
            [
                'singlepage' => 'title3',
                'multipage' => ['title3'],
                'singletitle' => 'title3',
                'multititle' => ['title3'],
            ]
        );
    }

    protected function prepareDatetime()
    {
        $this->loadSchemaJSON('datetime');
        $access = MockAccessTableAlias::getGlobalAccess('datetime');
        $access->saveData(['field' => '2023-01-01 12:00']);
        $access = MockAccessTableAlias::getGlobalAccess('datetime');
        $access->saveData(['field' => '2023-01-02 00:00']);
        $access = MockAccessTableAlias::getGlobalAccess('datetime');
        $access->saveData(['field' => '2023-01-02 12:00']);
    }
}
