<?php

namespace dokuwiki\plugin\struct\test;

/**
 * @group plugin_struct
 * @group plugins
 */
class helper_db_struct_test extends StructTest
{

    public function setUp(): void
    {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
        $this->loadSchemaJSON('schema2');
    }

    /**
     * @noinspection SqlDialectInspection
     * @noinspection SqlNoDataSourceInspection
     */
    public function test_json()
    {
        /** @var \helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        $sqlite = $helper->getDB();

        $result = $sqlite->queryValue("SELECT STRUCT_JSON('foo', 'bar') ");
        $expect = '["foo","bar"]';
        $this->assertEquals($expect, $result);

        $result = $sqlite->queryAll("SELECT STRUCT_JSON(id, tbl) AS col FROM schemas");

        $expect = [
            ['col' => '[1,"schema1"]'],
            ['col' => '[2,"schema2"]'],
        ];
        $this->assertEquals($expect, $result);
    }

}
