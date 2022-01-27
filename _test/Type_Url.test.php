<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\types\Url;

/**
 * Testing the Url Type
 *
 * @group plugin_struct
 * @group plugins
 */
class Type_Url_struct_test extends StructTest
{

    /**
     * Provides failing validation data
     *
     * @return array
     */
    public function validateFailProvider()
    {
        return array(
            array('foo', '', '', ''),
            array('http', '', '', ''),
            array('http://', '', '', ''),
            array('foo', 'pre', '', ''),
            array('foo', '', 'post', ''),
            array('foo', 'pre', 'post', ''),

            array('http://', '', '', 'http')
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
            array('http://www.example.com', '', '', ''),
            array('www.example.com', 'http://', '', ''),
            array('www.example.com', 'http://', 'bang', ''),
            array('http://www.example.com', '', 'bang', ''),

            array('foo', '', '', 'http'),
            array('http', '', '', 'http'),
            array('foo', 'pre', '', 'http'),
            array('foo', '', 'post', 'http'),
            array('foo', 'pre', 'post', 'http')
        );
    }

    /**
     * Provide data to test autoshortening feature
     *
     * @return array
     */
    public function generateAutoTitleProvider()
    {
        return [
            ['https://foobar.com', 'foobar.com'],
            ['https://foobar.com/', 'foobar.com'],
            ['https://www.foobar.com/', 'foobar.com'],
            ['https://www.foobar.com/test', 'foobar.com/…'],
            ['https://www.foobar.com/?test', 'foobar.com/…'],
            ['https://www.foobar.com/#hash', 'foobar.com/…'],
        ];
    }

    /**
     * @dataProvider validateFailProvider
     */
    public function test_validate_fail($value, $prefix, $postfix, $autoscheme)
    {
        $this->expectException(ValidationException::class);
        $url = new Url(array('prefix' => $prefix, 'postfix' => $postfix, 'autoscheme' => $autoscheme));
        $url->validate($value);
    }

    /**
     * @dataProvider validateSuccessProvider
     */
    public function test_validate_success($value, $prefix, $postfix, $autoscheme)
    {
        $url = new Url(array('prefix' => $prefix, 'postfix' => $postfix, 'autoscheme' => $autoscheme));
        $url->validate($value);
        $this->assertTrue(true); // we simply check that no exceptions are thrown
    }

    /**
     * @dataProvider generateAutoTitleProvider
     */
    public function test_generateAutoTitle($input, $title)
    {
        $url = new Url(['autoshorten' => true]);
        $result = $this->callInaccessibleMethod($url, 'generateTitle', [$input]);
        $this->assertSame($title, $result);

        $url = new Url(['autoshorten' => false]);
        $result = $this->callInaccessibleMethod($url, 'generateTitle', [$input]);
        $this->assertSame($input, $result);
    }

    public function test_generateFixedTitle()
    {
        $input = 'https://www.foobar.com/long';
        $title = 'oink';

        $url = new Url(['fixedtitle' => $title]);
        $result = $this->callInaccessibleMethod($url, 'generateTitle', [$input]);
        $this->assertSame($title, $result);
    }
}
