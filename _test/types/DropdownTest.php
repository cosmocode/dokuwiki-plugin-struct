<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\test\mock\AccessTable;
use dokuwiki\plugin\struct\test\mock\Dropdown;
use dokuwiki\plugin\struct\test\StructTest;

/**
 * Testing the Dropdown Type
 *
 * @group plugin_struct
 * @group plugins
 */
class DropdownTest extends StructTest
{

    protected function preparePages()
    {
        $this->loadSchemaJSON('dropdowns');
        $now = time();
        $this->saveData(
            'test1',
            'dropdowns',
            [
                'drop1' => '["test1",1]', 'drop2' => '["test1",1]', 'drop3' => 'John'
            ],
            $now
        );
        $this->saveData(
            'test2',
            'dropdowns',
            [
                'drop1' => '["test1",2]', 'drop2' => '["test1",2]', 'drop3' => 'Jane'
            ],
            $now
        );
        $this->saveData(
            'test3',
            'dropdowns',
            [
                'drop1' => '["test1",3]', 'drop2' => '["test1",3]', 'drop3' => 'Tarzan'
            ],
            $now
        );
    }


    public function test_data()
    {
        $this->preparePages();

        $access = AccessTable::getPageAccess('dropdowns', 'test1');
        $data = $access->getData();

        $this->assertEquals('John', $data['drop3']->getValue());
        $this->assertEquals('John', $data['drop3']->getRawValue());
        $this->assertEquals('John', $data['drop3']->getDisplayValue());

        $R = new \Doku_Renderer_xhtml();
        $data['drop3']->render($R, 'xhtml');
        $this->assertEquals('John', $R->doc);
    }


    public function test_getOptions()
    {
        // fixed values
        $dropdown = new Dropdown(
            [
                'values' => 'John, Jane, Tarzan',
            ],
            'test',
            false,
            0
        );
        $expect = array(
            '' => '',
            'Jane' => 'Jane',
            'John' => 'John',
            'Tarzan' => 'Tarzan'
        );
        $this->assertEquals($expect, $dropdown->getOptions());
    }

}
