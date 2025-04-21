<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\test\mock\Assignments;
use dokuwiki\plugin\struct\test\mock\Lookup;
use dokuwiki\plugin\struct\types\Decimal;
use dokuwiki\plugin\struct\types\Media;
use dokuwiki\plugin\struct\types\Text;

/**
 * Tests for the basic validation functions
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class ValidatorTest extends StructTest
{

    public function setUp(): void
    {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
        $this->loadSchemaJSON('schema2');

        $this->saveData(
            'page01',
            'schema1',
            [
                'first' => 'first data',
                'second' => ['second data', 'more data', 'even more'],
                'third' => 'third data',
                'fourth' => 'fourth data'
            ]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        /** @var \helper_plugin_struct_db $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $sqlite->resetDB();
        Assignments::reset();
    }

    public function test_validate_nonArray()
    {
        $label = 'label';
        $errormsg = sprintf($this->getLang('validation_prefix') . $this->getLang('Validation Exception Decimal needed'), $label);
        $integer = new Decimal();

        $validator = new mock\ValueValidator();
        $value = 'NaN';
        $this->assertFalse($validator->validateField($integer, $label, $value));
        $this->assertEquals([$errormsg], $validator->getErrors());
    }

    public function test_validate_array()
    {
        $label = 'label';
        $errormsg = sprintf($this->getLang('validation_prefix') . $this->getLang('Validation Exception Decimal needed'), $label);
        $integer = new Decimal();

        $validator = new mock\ValueValidator();
        $value = ['NaN', 'NaN'];
        $this->assertFalse($validator->validateField($integer, $label, $value));
        $this->assertEquals([$errormsg, $errormsg], $validator->getErrors());
    }

    public function test_validate_blank()
    {
        $integer = new Decimal();

        $validator = new mock\ValueValidator();
        $value = null;
        $this->assertTrue($validator->validateField($integer, 'label', $value));
        $this->assertEquals([], $validator->getErrors());
    }

    public function test_validate_clean()
    {
        $text = new Text();

        $validator = new mock\ValueValidator();
        $value = '  foo  ';
        $this->assertTrue($validator->validateField($text, 'label', $value));
        $this->assertEquals('foo', $value);

        $value = ['  foo  ', '  bar  '];
        $this->assertTrue($validator->validateField($text, 'label', $value));
        $this->assertEquals(['foo', 'bar'], $value);
    }

    public function test_validate_empty_multivalue()
    {
        $lookup = new Lookup(null, '', true);
        $col = new Column(10, $lookup);

        $validator = new mock\ValueValidator();
        $value = '';

        $validator->validateValue($col, $value);
        $this->assertEquals([''], $value);

        // some fields like media or date can post an array with multiple empty strings
        // because they use multiple inputs instead of comma separation in one input
        $media = new Media(null, '', true);
        $col = new Column(10, $media);

        $validator = new mock\ValueValidator();
        $value = ['', '', ''];

        $validator->validateValue($col, $value);
        $this->assertEquals([''], $value);
    }

}
