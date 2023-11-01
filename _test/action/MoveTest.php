<?php

namespace dokuwiki\plugin\struct\test\action;

use dokuwiki\plugin\struct\meta;
use dokuwiki\plugin\struct\test\StructTest;

/**
 * Tests for the move plugin support of the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 * @covers action_plugin_struct_move
 */
class MoveTest extends StructTest
{

    protected $data1 = [
        'page' => 'wiki:syntax',
        'pages' => ['wiki:syntax', 'wiki:welcome'],
        'lookup' => '["page1",0]',
        'lookups' => ['["page1",0]', '["page2",0]'],
        'media' => 'wiki:logo.png',
        'medias' => ['wiki:logo.png'],
        'title' => 'wiki:syntax',
        'titles' => ['wiki:syntax', 'wiki:welcome']
    ];

    protected $data2 = [
        'page' => 'wiki:syntax#something',
        'pages' => ['wiki:syntax#something', 'wiki:welcome#something'],
        'lookup' => '["page1",0]',
        'lookups' => ['["page1",0]', '["page2",0]'],
        'media' => 'wiki:logo.png',
        'medias' => ['wiki:logo.png'],
        'title' => 'wiki:syntax#something',
        'titles' => ['wiki:syntax#something', 'wiki:welcome#something']
    ];

    protected $empty = [
        'page' => '',
        'pages' => [],
        'lookup' => '',
        'lookups' => [],
        'media' => '',
        'medias' => [],
        'title' => '',
        'titles' => []
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->loadSchemaJSON('moves');

        $schemaData = meta\AccessTable::getPageAccess('moves', 'page1');
        $schemaData->saveData($this->data1);

        $schemaData = meta\AccessTable::getPageAccess('moves', 'page2');
        $schemaData->saveData($this->data2);
    }

    public function test_selfmove()
    {
        // fake move event
        $evdata = ['src_id' => 'page1', 'dst_id' => 'page3'];
        $event = new \Doku_Event('PLUGIN_MOVE_PAGE_RENAME', $evdata);
        $event->trigger();

        // old page should be gone
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page1');
        $this->assertEquals($this->empty, $schemaData->getDataArray());

        // new page should have adjusted data
        $data = $this->data1;
        $data['lookup'] = '["page3",0]';
        $data['lookups'] = ['["page3",0]', '["page2",0]'];
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page3');
        $this->assertEquals($data, $schemaData->getDataArray());

        // other page should have adjusted lookups
        $data = $this->data2;
        $data['lookup'] = '["page3",0]';
        $data['lookups'] = ['["page3",0]', '["page2",0]'];
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page2');
        $this->assertEquals($data, $schemaData->getDataArray());
    }

    public function test_pagemove()
    {
        // fake move event
        $evdata = ['src_id' => 'wiki:syntax', 'dst_id' => 'foobar'];
        $event = new \Doku_Event('PLUGIN_MOVE_PAGE_RENAME', $evdata);
        $event->trigger();

        $data = $this->data1;
        $data['page'] = 'foobar';
        $data['pages'] = ['foobar', 'wiki:welcome'];
        $data['title'] = 'foobar';
        $data['titles'] = ['foobar', 'wiki:welcome'];
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page1');
        $this->assertEquals($data, $schemaData->getDataArray());

        $data = $this->data2;
        $data['page'] = 'foobar#something';
        $data['pages'] = ['foobar#something', 'wiki:welcome#something'];
        $data['title'] = 'foobar#something';
        $data['titles'] = ['foobar#something', 'wiki:welcome#something'];
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page2');
        $this->assertEquals($data, $schemaData->getDataArray());
    }

    public function test_mediamove()
    {
        // fake move event
        $evdata = ['src_id' => 'wiki:logo.png', 'dst_id' => 'foobar.png'];
        $event = new \Doku_Event('PLUGIN_MOVE_MEDIA_RENAME', $evdata);
        $event->trigger();

        $data = $this->data1;
        $data['media'] = 'foobar.png';
        $data['medias'] = ['foobar.png'];
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page1');
        $this->assertEquals($data, $schemaData->getDataArray());

        $data = $this->data2;
        $data['media'] = 'foobar.png';
        $data['medias'] = ['foobar.png'];
        $schemaData = meta\AccessTable::getPageAccess('moves', 'page2');
        $this->assertEquals($data, $schemaData->getDataArray());
    }
}
