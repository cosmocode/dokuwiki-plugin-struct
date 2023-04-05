<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\test\StructTest;

/**
 * @group plugin_struct
 * @group plugins
 */
class TagTest extends StructTest
{

    public function setUp(): void
    {
        parent::setUp();
        $this->loadSchemaJSON('tag');

        $this->waitForTick();
        $this->saveData('page1', 'tag', ['tag' => 'Aragorn', 'tags' => ['Faramir', 'Gollum']], time());
        $this->saveData('page2', 'tag', ['tag' => 'Eldarion', 'tags' => ['Saruman', 'Arwen']], time());
        $this->waitForTick();
        $this->saveData('page1', 'tag', ['tag' => 'Treebeard', 'tags' => ['Frodo', 'Arwen']], time());
    }


    public function test_autocomplete()
    {
        global $INPUT;
        $schema = new Schema('tag');

        // search tag field, should not find Aragon because tag is not in current revision
        $INPUT->set('search', 'ar');
        $tag = $schema->findColumn('tag')->getType();
        $return = $tag->handleAjax();
        $expect = [
            ['label' => 'Eldarion', 'value' => 'Eldarion'],
            ['label' => 'Treebeard', 'value' => 'Treebeard'],
        ];
        $this->assertEquals($expect, $return);

        // multi value
        $INPUT->set('search', 'ar');
        $tag = $schema->findColumn('tags')->getType();
        $return = $tag->handleAjax();
        $expect = [
            ['label' => 'Arwen', 'value' => 'Arwen'],
            ['label' => 'Saruman', 'value' => 'Saruman'],
        ];
        $this->assertEquals($expect, $return);

    }
}
