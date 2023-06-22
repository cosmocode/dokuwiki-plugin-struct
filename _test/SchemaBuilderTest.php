<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\SchemaBuilder;

/**
 * @group plugin_struct
 * @group plugins
 *
 */
class SchemaBuilderTest extends StructTest
{

    /** @var \helper_plugin_sqlite $sqlite */
    protected $sqlite;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \helper_plugin_struct_db $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $this->sqlite = $sqlite->getDB();
    }

    /**
     * @noinspection SqlDialectInspection
     * @noinspection SqlNoDataSourceInspection
     */
    public function test_build_new()
    {

        // arrange
        $testdata = [];
        $testdata['new']['new1']['sort'] = 70;
        $testdata['new']['new1']['label'] = 'testcolumn';
        $testdata['new']['new1']['ismulti'] = 0;
        $testdata['new']['new1']['config'] = '{"prefix": "", "postfix": ""}';
        $testdata['new']['new1']['class'] = 'Text';
        $testdata['new']['new1']['isenabled'] = '1';
        $testdata['new']['new2']['sort'] = 40;
        $testdata['new']['new2']['label'] = 'testMulitColumn';
        $testdata['new']['new2']['ismulti'] = 1;
        $testdata['new']['new2']['config'] = '{"prefix": "pre", "postfix": "post"}';
        $testdata['new']['new2']['class'] = 'Text';
        $testdata['new']['new2']['isenabled'] = '1';

        $testname = 'testTable';
        $testname = Schema::cleanTableName($testname);

        // act
        $builder = new SchemaBuilder($testname, $testdata);
        $result = $builder->build();

        /** @noinspection SqlResolve */
        $tableSQL = $this->sqlite->queryValue("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", ['data_' . $testname]);
        $expected_tableSQL = "CREATE TABLE data_testtable (
                    pid TEXT DEFAULT '',
                    rid INTEGER,
                    rev INTEGER,
                    latest BOOLEAN NOT NULL DEFAULT 0,
                    published BOOLEAN DEFAULT NULL, col1 DEFAULT '', col2 DEFAULT '',
                    PRIMARY KEY(pid, rid, rev)
                )";

        $actual_types = $this->sqlite->queryAll("SELECT * FROM types");
        $expected_types = [
            [
                'id' => "1",
                'class' => 'Text',
                'ismulti' => "0",
                'label' => "testcolumn",
                'config' => '{"prefix": "", "postfix": ""}'
            ],
            [
                'id' => "2",
                'class' => 'Text',
                'ismulti' => "1",
                'label' => "testMulitColumn",
                'config' => '{"prefix": "pre", "postfix": "post"}'
            ]
        ];

        $actual_cols = $this->sqlite->queryAll("SELECT * FROM schema_cols");
        $expected_cols = [
            [
                'sid' => "1",
                'colref' => "1",
                'enabled' => "1",
                'tid' => "1",
                'sort' => "70"
            ],
            [
                'sid' => "1",
                'colref' => "2",
                'enabled' => "1",
                'tid' => "2",
                'sort' => "40"
            ]
        ];

        $actual_schema = $this->sqlite->queryRecord("SELECT * FROM schemas");

        $this->assertSame(1, $result);
        $this->assertEquals($expected_tableSQL, $tableSQL);
        $this->assertEquals($expected_types, $actual_types);
        $this->assertEquals($expected_cols, $actual_cols);
        $this->assertEquals(1, $actual_schema['id']);
        $this->assertEquals($testname, $actual_schema['tbl']);
        $this->assertTrue((int)$actual_schema['ts'] > 0, 'timestamp should be larger than 0');
    }

    /**
     * @noinspection SqlDialectInspection
     * @noinspection SqlNoDataSourceInspection
     */
    public function test_build_update()
    {

        // arrange
        $initialdata = [];
        $initialdata['new']['new1']['sort'] = 70;
        $initialdata['new']['new1']['label'] = 'testcolumn';
        $initialdata['new']['new1']['ismulti'] = 0;
        $initialdata['new']['new1']['config'] = '{"prefix": "", "postfix": ""}';
        $initialdata['new']['new1']['class'] = 'Text';
        $initialdata['new']['new1']['isenabled'] = '1';

        $testname = 'testTable';
        $testname = Schema::cleanTableName($testname);

        $builder = new SchemaBuilder($testname, $initialdata);
        $result = $builder->build();
        $this->assertSame(1, $result, 'Prerequiste setup  in order to have basis which to change during act');

        $updatedata = [];
        $updatedata['id'] = "1";
        $updatedata['cols']['1']['sort'] = 65;
        $updatedata['cols']['1']['label'] = 'testColumn';
        $updatedata['cols']['1']['ismulti'] = 1;
        $updatedata['cols']['1']['config'] = '{"prefix": "pre", "postfix": "fix"}';
        $updatedata['cols']['1']['class'] = 'Text';
        $updatedata['cols']['1']['isenabled'] = '1';

        // act
        $builder = new SchemaBuilder($testname, $updatedata);
        $result = $builder->build();

        $actual_types = $this->sqlite->queryAll("SELECT * FROM types");
        $expected_types = [
            [
                'id' => "1",
                'class' => 'Text',
                'ismulti' => "0",
                'label' => "testcolumn",
                'config' => '{"prefix": "", "postfix": ""}'
            ],
            [
                'id' => "2",
                'class' => 'Text',
                'ismulti' => "1",
                'label' => "testColumn",
                'config' => '{"prefix": "pre", "postfix": "fix"}'
            ]
        ];

        // assert
        $this->assertSame(2, $result);
        $this->assertEquals($expected_types, $actual_types);
    }
}
