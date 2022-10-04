<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\test\mock\Search;
use dokuwiki\plugin\struct\types\Decimal;

/**
 * Testing the Decimal Type
 *
 * @group plugin_struct
 * @group plugins
 */
class Type_Decimal_struct_test extends StructTest
{

    /**
     * Provides failing validation data
     *
     * @return array
     */
    public function validateFailProvider()
    {
        return array(
            // same as integer:
            array('foo', '', ''),
            array('foo222', '', ''),
            array('-5', '0', ''),
            array('5', '', '0'),
            array('500', '100', '200'),
            array('50', '100', '200'),
            // decimal specifics
            array('5.5', '5.6', ''),
            array('5,5', '5.6', ''),
            array('-5.5', '-5.4', ''),
            array('-5,5', '-5.4', ''),
        );
    }

    /**
     * Provides successful validation data
     *
     * @return array
     */
    public function validateSuccessProvider()
    {
        return array(
            // same as integer
            array('0', '', ''),
            array('-5', '', ''),
            array('5', '', ''),
            array('5', '0', ''),
            array('-5', '', '0'),
            array('150', '100', '200'),
            // decimal specifics
            array('5.5', '', ''),
            array('5,5', '', ''),
            array('-5.5', '', ''),
            array('-5,5', '', ''),
            array('5.5', '4.5', ''),
            array('5,5', '4.5', ''),
            array('-5.5', '', '4.5'),
            array('-5,5', '', '4.5'),
            array('5.5645000', '', ''),
            // boundaries
            array('0', '0', ''),
            array('0', '', '0'),
            array('5', '5', ''),
            array('5', '', '5'),
            array('0', '0.0', ''),
            array('0', '', '0.0'),
            array('5.0', '5.0', ''),
            array('5.0', '', '5.0'),
        );
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
        return array(
            // $value, $expect, $roundto, $decpoint, $thousands, $trimzeros, $prefix='', $postfix='', $engineering = false
            array('5000', '5 000,00', '2', ',', ' ', false),
            array('5000', '5 000', '2', ',', ' ', true),
            array('5000', '5 000', '0', ',', ' ', false),
            array('5000', '5 000', '0', ',', ' ', true),
            array('5000', '5 000', '-1', ',', ' ', false),
            array('5000', '5 000', '-1', ',', ' ', true),

            array('-0.55600', '-0,56', '2', ',', ' ', false),
            array('-0.55600', '-0,55600', '-1', ',', ' ', false),
            array('-0.55600', '-0,556', '-1', ',', ' ', true),
            array('-0.55600', '-0,5560', '4', ',', ' ', false),
            array('-0.55600', '-0,556', '4', ',', ' ', true),

            array('-0.55600', '$ -0,556', '4', ',', ' ', true, '$ '),
            array('-0.55600', '-0,556 EUR', '4', ',', ' ', true, '', ' EUR'),

            //engineering notation
            array('1e-18', '1'."\xE2\x80\xAF".'a', '-1', ',', ' ', true, '', '', true),
            array('1e-15', '1'."\xE2\x80\xAF".'f', '-1', ',', ' ', true, '', '', true),
            array('1e-12', '1'."\xE2\x80\xAF".'p', '-1', ',', ' ', true, '', '', true),
            array('1e-9',  '1'."\xE2\x80\xAF".'n', '-1', ',', ' ', true, '', '', true),
            array('1e-6',  '1'."\xE2\x80\xAF".'µ', '-1', ',', ' ', true, '', '', true),
            array('1e-3',  '1'."\xE2\x80\xAF".'m', '-1', ',', ' ', true, '', '', true),

            array('1e3',  '1'."\xE2\x80\xAF".'k', '-1', ',', ' ', true, '', '', true),
            array('1e6',  '1'."\xE2\x80\xAF".'M', '-1', ',', ' ', true, '', '', true),
            array('1e9',  '1'."\xE2\x80\xAF".'G', '-1', ',', ' ', true, '', '', true),
            array('1e12', '1'."\xE2\x80\xAF".'T', '-1', ',', ' ', true, '', '', true),

            array('1e4', '10'. "\xE2\x80\xAF".'k', '-1', ',', ' ', true, '', '', true),
            array('1e5', '100'."\xE2\x80\xAF".'k', '-1', ',', ' ', true, '', '', true),

            array('1e-4', '100'."\xE2\x80\xAF".'µ', '-1', ',', ' ', true, '', '', true),
            array('1e-5', '10'. "\xE2\x80\xAF".'µ', '-1', ',', ' ', true, '', '', true),
            
            //test behaviour if number exceeds prefix array
            array('1e15',  '1000'. "\xE2\x80\xAF".'T', '-1', ',', ' ', true, '', '', true),
            array('1e-21', '0.001'."\xE2\x80\xAF".'a', '-1', ',', ' ', true, '', '', true),
            
        );
    }

    /**
     * @dataProvider valueProvider
     */
    public function test_renderValue($value, $expect, $roundto, $decpoint, $thousands, $trimzeros, $prefix = '', $postfix = '', $engineering = false)
    {
        $decimal = new Decimal(array(
            'roundto' => $roundto,
            'decpoint' => $decpoint,
            'thousands' => $thousands,
            'trimzeros' => $trimzeros,
            'prefix' => $prefix,
            'postfix' => $postfix,
            'engineering' => $engineering
        ));
        $R = new \Doku_Renderer_xhtml();
        $R->doc = '';
        $decimal->renderValue($value, $R, 'xhtml');
        $this->assertEquals($expect, $R->doc);
    }

    public function test_sort()
    {
        $this->loadSchemaJSON('decimal');
        $this->waitForTick();
        $this->saveData('page1', 'decimal', array('field' => '5000'));
        $this->saveData('page2', 'decimal', array('field' => '5000.001'));
        $this->saveData('page3', 'decimal', array('field' => '900.5'));
        $this->saveData('page4', 'decimal', array('field' => '1.5'));

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
        $this->saveData('page1', 'decimal', array('field' => '5000'));
        $this->saveData('page2', 'decimal', array('field' => '5000.001'));
        $this->saveData('page3', 'decimal', array('field' => '900.5'));
        $this->saveData('page4', 'decimal', array('field' => '1.5'));

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
