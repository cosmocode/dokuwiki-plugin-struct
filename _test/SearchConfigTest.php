<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;
use dokuwiki\plugin\struct\test\mock\SearchConfig;

/**
 * @group plugin_struct
 * @group plugins
 *
 */
class SearchConfigTest extends StructTest
{

    public function test_filtervars_simple()
    {
        global $INFO;
        $INFO['id'] = 'foo:bar:baz';

        $searchConfig = new SearchConfig([]);

        $this->assertEquals('foo:bar:baz', $searchConfig->applyFilterVars('$ID$'));
        $this->assertEquals('baz', $searchConfig->applyFilterVars('$PAGE$'));
        $this->assertEquals('foo:bar', $searchConfig->applyFilterVars('$NS$'));
        $this->assertEquals(date('Y-m-d'), $searchConfig->applyFilterVars('$TODAY$'));
        $this->assertEquals('', $searchConfig->applyFilterVars('$USER$'));
        $_SERVER['REMOTE_USER'] = 'user';
        $this->assertEquals('user', $searchConfig->applyFilterVars('$USER$'));

        $this->assertEquals('user baz', $searchConfig->applyFilterVars('$USER$ $PAGE$'));
        $this->assertEquals('$user', $searchConfig->applyFilterVars('$user'));
        $this->assertEquals(date('Y-m-d'), $searchConfig->applyFilterVars('$DATE(now)$'));
    }

    public function test_filtervars_struct()
    {
        global $INFO;
        $INFO['id'] = 'foo:bar:baz';

        // prepare some struct data
        $sb = new meta\SchemaImporter('schema1', file_get_contents(__DIR__ . '/json/schema1.struct.json'));
        $sb->build();
        $schemaData = meta\AccessTable::getPageAccess('schema1', $INFO['id'], time());
        $schemaData->saveData(
            [
                'first' => 'test',
                'second' => ['multi1', 'multi2']
            ]
        );

        $searchConfig = new SearchConfig(['schemas' => [['schema1', 'alias']]]);
        $this->assertEquals('test', $searchConfig->applyFilterVars('$STRUCT.first$'));
        $this->assertEquals('test', $searchConfig->applyFilterVars('$STRUCT.alias.first$'));
        $this->assertEquals('test', $searchConfig->applyFilterVars('$STRUCT.schema1.first$'));

        $this->assertEquals('pretestpost', $searchConfig->applyFilterVars('pre$STRUCT.first$post'));
        $this->assertEquals('pretestpost', $searchConfig->applyFilterVars('pre$STRUCT.alias.first$post'));
        $this->assertEquals('pretestpost', $searchConfig->applyFilterVars('pre$STRUCT.schema1.first$post'));

        $this->assertEquals(['multi1', 'multi2'], $searchConfig->applyFilterVars('$STRUCT.second$'));
        $this->assertEquals(['multi1', 'multi2'], $searchConfig->applyFilterVars('$STRUCT.alias.second$'));
        $this->assertEquals(['multi1', 'multi2'], $searchConfig->applyFilterVars('$STRUCT.schema1.second$'));

        $this->assertEquals(['premulti1post', 'premulti2post'], $searchConfig->applyFilterVars('pre$STRUCT.second$post'));
        $this->assertEquals(['premulti1post', 'premulti2post'], $searchConfig->applyFilterVars('pre$STRUCT.alias.second$post'));
        $this->assertEquals(['premulti1post', 'premulti2post'], $searchConfig->applyFilterVars('pre$STRUCT.schema1.second$post'));

        $this->assertEquals('', $searchConfig->applyFilterVars('$STRUCT.notexisting$'));
    }

    public function test_filtervars_struct_other()
    {
        global $INFO;
        $INFO['id'] = 'foo:bar:baz';

        // prepare some struct data
        $sb = new meta\SchemaImporter('schema2', file_get_contents(__DIR__ . '/json/schema2.struct.json'));
        $sb->build();
        $sb = new meta\SchemaImporter('schema3', file_get_contents(__DIR__ . '/json/schema2int.struct.json'));
        $sb->build();
        $schemaData = meta\AccessTable::getPageAccess('schema2', $INFO['id'], time());
        $schemaData->saveData(
            [
                'afirst' => 'test',
                'asecond' => ['multi1', 'multi2']
            ]
        );
        $schemaData = meta\AccessTable::getPageAccess('schema3', 'foo:test:baz', time());
        $schemaData->saveData(
            [
                'afirst' => 'test1',
                'asecond' => ['multi1a', 'multi2a']
            ]
        );

        $searchConfig = new SearchConfig(['schemas' => [['schema3', 'alias']]]);
        $this->assertEquals('', $searchConfig->applyFilterVars('$STRUCT.afirst$'));
        $this->assertEquals('test', $searchConfig->applyFilterVars('$STRUCT.schema2.afirst$'));

        $this->assertEquals('prepost', $searchConfig->applyFilterVars('pre$STRUCT.afirst$post'));
        $this->assertEquals('pretestpost', $searchConfig->applyFilterVars('pre$STRUCT.schema2.afirst$post'));

        $this->assertEquals('', $searchConfig->applyFilterVars('$STRUCT.asecond$'));
        $this->assertEquals(['multi1', 'multi2'], $searchConfig->applyFilterVars('$STRUCT.schema2.asecond$'));

        $this->assertEquals('prepost', $searchConfig->applyFilterVars('pre$STRUCT.asecond$post'));
        $this->assertEquals(['premulti1post', 'premulti2post'], $searchConfig->applyFilterVars('pre$STRUCT.schema2.asecond$post'));

        $this->assertEquals('', $searchConfig->applyFilterVars('$STRUCT.notexisting$'));

        $this->assertEquals('', $searchConfig->applyFilterVars('$STRUCT.afirst$'));
        $this->assertEquals('test', $searchConfig->applyFilterVars('$STRUCT.schema2.afirst$'));
    }

    public function test_filtervars_user()
    {
        global $INFO, $USERINFO;

        $searchConfig = new SearchConfig([]);

        $_SERVER['REMOTE_USER'] = 'john';
        $USERINFO['name'] = 'John Smith';
        $USERINFO['mail'] = 'john.smith@example.com';
        $USERINFO['grps'] = ['user', 'test'];
        //update info array
        $INFO['userinfo'] = $USERINFO;

        $this->assertEquals('John Smith', $searchConfig->applyFilterVars('$USER.name$'));
        $this->assertEquals('john.smith@example.com', $searchConfig->applyFilterVars('$USER.mail$'));
        $this->assertEquals(['user', 'test'], $searchConfig->applyFilterVars('$USER.grps$'));
    }

    public function test_cacheflags()
    {
        $searchConfig = new SearchConfig([]);

        $flag = $searchConfig->determineCacheFlag(['foo', 'bar']);
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_DEFAULT));
        $this->assertFalse((bool)($flag & SearchConfig::$CACHE_USER));
        $this->assertFalse((bool)($flag & SearchConfig::$CACHE_DATE));

        $flag = $searchConfig->determineCacheFlag(['foo', '$USER$']);
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_DEFAULT));
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_USER));
        $this->assertFalse((bool)($flag & SearchConfig::$CACHE_DATE));

        $flag = $searchConfig->determineCacheFlag(['foo', '$TODAY$']);
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_DEFAULT));
        $this->assertFalse((bool)($flag & SearchConfig::$CACHE_USER));
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_DATE));

        $flag = $searchConfig->determineCacheFlag(['foo', '$TODAY$', '$USER$']);
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_DEFAULT));
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_USER));
        $this->assertTrue((bool)($flag & SearchConfig::$CACHE_DATE));
    }
}
