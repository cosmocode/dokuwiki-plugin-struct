<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\types\Text;

/**
 * @group plugin_struct
 * @group plugins
 */
class ValueTest extends StructTest
{

    /**
     * @param bool $multi
     * @return Column
     */
    protected function makeColumn($multi)
    {
        return new Column(10, new Text(null, '', $multi));
    }

    /**
     * Test setting and getting multi values
     */
    public function test_multi()
    {
        $col = $this->makeColumn(true);
        $val = new Value($col, ['one', 'two']);
        $this->assertSame($col, $val->getColumn());
        $this->assertEquals(['one', 'two'], $val->getValue());

        $val->setValue(['one', '', 'two', '']);
        $this->assertEquals(['one', 'two'], $val->getValue());

        $val->setValue(['one', '0', 'two']);
        $this->assertEquals(['one', '0', 'two'], $val->getValue());

        $val->setValue(['', null, false, "   \n"]);
        $this->assertEquals([], $val->getValue());

        $val->setValue('');
        $this->assertEquals([], $val->getValue());

        $val->setValue('0');
        $this->assertEquals(['0'], $val->getValue());

        $val->setValue(0);
        $this->assertEquals(['0'], $val->getValue());

        $val->setValue([]);
        $this->assertEquals([], $val->getValue());
    }

    /**
     * Test setting and getting single values
     */
    public function test_single()
    {
        $col = $this->makeColumn(false);
        $val = new Value($col, 'one');
        $this->assertSame($col, $val->getColumn());
        $this->assertEquals('one', $val->getValue());

        $val->setValue('0');
        $this->assertEquals('0', $val->getValue());

        $val->setValue('');
        $this->assertEquals('', $val->getValue());

        $val->setValue("   \n");
        $this->assertEquals('', $val->getValue());

        $val->setValue(null);
        $this->assertEquals('', $val->getValue());

        $val->setValue(false);
        $this->assertEquals('', $val->getValue());

        $val->setValue(['what', 'the', 'foo']);
        $this->assertEquals('what', $val->getValue());

        $val->setValue([]);
        $this->assertEquals('', $val->getValue());

        $val->setValue("  * hi\n  * ho");
        $this->assertEquals("  * hi\n  * ho", $val->getValue());
    }

    /**
     * empty values should not render
     */
    public function test_blankrender()
    {
        $R = new \Doku_Renderer_xhtml();

        $val = new Value($this->makeColumn(false), '');
        $val->render($R, 'xhtml');
        $this->assertEquals('', $R->doc);

        $val = new Value($this->makeColumn(true), array());
        $val->render($R, 'xhtml');
        $this->assertEquals('', $R->doc);
    }
}
