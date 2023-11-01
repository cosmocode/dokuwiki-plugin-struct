<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\test\mock\Search;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\Decimal;

/**
 * Testing the Decimal Type
 *
 * @group plugin_struct
 * @group plugins
 */
class DecimalTest extends StructTest
{

    /**
     * Provides failing min/max validation data
     *
     * @return array
     */
    public function validateFailProvider()
    {
        return [
            // same as integer:
            ['foo', '', ''],
            ['foo222', '', ''],
            ['-5', '0', ''],
            ['5', '', '0'],
            ['500', '100', '200'],
            ['50', '100', '200'],
            // decimal specifics
            ['5.5', '5.6', ''],
            ['5,5', '5.6', ''],
            ['-5.5', '-5.4', ''],
            ['-5,5', '-5.4', ''],
        ];
    }

    /**
     * Provides successful min/max validation data
     *
     * @return array
     */
    public function validateSuccessProvider()
    {
        return [
            // same as integer
            ['0', '', ''],
            ['-5', '', ''],
            ['5', '', ''],
            ['5', '0', ''],
            ['-5', '', '0'],
            ['150', '100', '200'],
            // decimal specifics
            ['5.5', '', ''],
            ['5,5', '', ''],
            ['-5.5', '', ''],
            ['-5,5', '', ''],
            ['5.5', '4.5', ''],
            ['5,5', '4.5', ''],
            ['-5.5', '', '4.5'],
            ['-5,5', '', '4.5'],
            ['5.5645000', '', ''],
            // boundaries
            ['0', '0', ''],
            ['0', '', '0'],
            ['5', '5', ''],
            ['5', '', '5'],
            ['0', '0.0', ''],
            ['0', '', '0.0'],
            ['5.0', '5.0', ''],
            ['5.0', '', '5.0'],
        ];
    }


    /**
     * @dataProvider validateFailProvider
     */
    public function test_validate_fail($value, $min, $max)
    {
        $this->expectException(ValidationException::class);
        $decimal = new Decimal(array('min' => $min, 'max' => $max));
        $decimal->validate($value);
    }

    /**
     * @dataProvider validateSuccessProvider
     */
    public function test_validate_success($value, $min, $max, $decpoint = '.')
    {
        $decimal = new Decimal(array('min' => $min, 'max' => $max));
        $decimal->validate($value);
        $this->assertTrue(true); // we simply check that no exceptions are thrown
    }


    public function valueProvider()
    {
        return [
            // $value, $expect, $roundto, $decpoint, $thousands, $trimzeros, $prefix='', $postfix='', $engineering = false
            ['5000', '5 000,00', '2', ',', ' ', false],
            ['5000', '5 000', '2', ',', ' ', true],
            ['5000', '5 000', '0', ',', ' ', false],
            ['5000', '5 000', '0', ',', ' ', true],
            ['5000', '5 000', '', ',', ' ', false],
            ['5000', '5 000', '', ',', ' ', true],
            ['5000', '5 000', '-1', ',', ' ', false],
            ['5000', '5 000', '-1', ',', ' ', true],

            ['777.707', '778', '0', ',', ' ', true],
            ['777.707', '778', '', ',', ' ', true],
            ['777.707', '777,71', '2', ',', ' ', true],
            ['777.707', '777,71', '2', ',', ' ', false],

            ['-0.55600', '-0,56', '2', ',', ' ', false],
            ['-0.55600', '-0,55600', '-1', ',', ' ', false],
            ['-0.55600', '-0,556', '-1', ',', ' ', true],
            ['-0.55600', '-0,5560', '4', ',', ' ', false],
            ['-0.55600', '-0,556', '4', ',', ' ', true],

            ['-0.55600', '$ -0,556', '4', ',', ' ', true, '$ '],
            ['-0.55600', '-0,556 EUR', '4', ',', ' ', true, '', ' EUR'],

            //engineering notation
            ['1e-18', '1' . "\xE2\x80\xAF" . 'a', '-1', ',', ' ', true, '', '', true],
            ['1e-15', '1' . "\xE2\x80\xAF" . 'f', '-1', ',', ' ', true, '', '', true],
            ['1e-12', '1' . "\xE2\x80\xAF" . 'p', '-1', ',', ' ', true, '', '', true],
            ['1e-9', '1' . "\xE2\x80\xAF" . 'n', '-1', ',', ' ', true, '', '', true],
            ['1e-6', '1' . "\xE2\x80\xAF" . 'µ', '-1', ',', ' ', true, '', '', true],
            ['1e-3', '1' . "\xE2\x80\xAF" . 'm', '-1', ',', ' ', true, '', '', true],

            ['1e3', '1' . "\xE2\x80\xAF" . 'k', '-1', ',', ' ', true, '', '', true],
            ['1e6', '1' . "\xE2\x80\xAF" . 'M', '-1', ',', ' ', true, '', '', true],
            ['1e9', '1' . "\xE2\x80\xAF" . 'G', '-1', ',', ' ', true, '', '', true],
            ['1e12', '1' . "\xE2\x80\xAF" . 'T', '-1', ',', ' ', true, '', '', true],

            ['1e4', '10' . "\xE2\x80\xAF" . 'k', '-1', ',', ' ', true, '', '', true],
            ['1e5', '100' . "\xE2\x80\xAF" . 'k', '-1', ',', ' ', true, '', '', true],

            ['1e-4', '100' . "\xE2\x80\xAF" . 'µ', '-1', ',', ' ', true, '', '', true],
            ['1e-5', '10' . "\xE2\x80\xAF" . 'µ', '-1', ',', ' ', true, '', '', true],

            //test behaviour if number exceeds prefix array
            ['1e15', '1000' . "\xE2\x80\xAF" . 'T', '-1', ',', ' ', true, '', '', true],
            ['1e-21', '0.001' . "\xE2\x80\xAF" . 'a', '-1', ',', ' ', true, '', '', true],

        ];
    }

    /**
     * @dataProvider valueProvider
     */
    public function test_renderValue(
        $value, $expect, $roundto, $decpoint,
        $thousands, $trimzeros,
        $prefix = '', $postfix = '', $engineering = false
    )
    {
        $decimal = new Decimal([
            'roundto' => $roundto,
            'decpoint' => $decpoint,
            'thousands' => $thousands,
            'trimzeros' => $trimzeros,
            'prefix' => $prefix,
            'postfix' => $postfix,
            'engineering' => $engineering
        ]);
        $R = new \Doku_Renderer_xhtml();
        $R->doc = '';
        $decimal->renderValue($value, $R, 'xhtml');
        $this->assertEquals($expect, $R->doc);
    }

    public function test_sort()
    {
        $this->loadSchemaJSON('decimal');
        $this->waitForTick();
        $this->saveData('page1', 'decimal', ['field' => '5000']);
        $this->saveData('page2', 'decimal', ['field' => '5000.001']);
        $this->saveData('page3', 'decimal', ['field' => '900.5']);
        $this->saveData('page4', 'decimal', ['field' => '1.5']);

        $search = new Search();
        $search->addSchema('decimal');
        $search->addColumn('%pageid%');
        $search->addColumn('field');
        $search->addSort('field', true);
        /** @var Value[][] $result */
        $result = $search->execute();

        $this->assertEquals(4, count($result));
        $this->assertEquals('page4', $result[0][0]->getValue());
        $this->assertEquals('page3', $result[1][0]->getValue());
        $this->assertEquals('page1', $result[2][0]->getValue());
        $this->assertEquals('page2', $result[3][0]->getValue());
    }

    public function test_filter()
    {
        $this->loadSchemaJSON('decimal');
        $this->waitForTick();
        $this->saveData('page1', 'decimal', ['field' => '5000']);
        $this->saveData('page2', 'decimal', ['field' => '5000.001']);
        $this->saveData('page3', 'decimal', ['field' => '900.5']);
        $this->saveData('page4', 'decimal', ['field' => '1.5']);

        $search = new Search();
        $search->addSchema('decimal');
        $search->addColumn('%pageid%');
        $search->addColumn('field');
        $search->addFilter('field', '800', '>', 'AND');
        $search->addSort('field', true);
        /** @var Value[][] $result */
        $result = $search->execute();

        $this->assertEquals(3, count($result));
        $this->assertEquals('page3', $result[0][0]->getValue());
        $this->assertEquals('page1', $result[1][0]->getValue());
        $this->assertEquals('page2', $result[2][0]->getValue());
    }

}
