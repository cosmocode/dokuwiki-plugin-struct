<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\Url;

/**
 * Testing the Url Type
 *
 * @group plugin_struct
 * @group plugins
 */
class UrlTest extends StructTest
{

    /**
     * Provides failing validation data
     *
     * @return array
     */
    public function validateFailProvider()
    {
        return [
            ['foo', '', '', ''],
            ['http', '', '', ''],
            ['http://', '', '', ''],
            ['foo', 'pre', '', ''],
            ['foo', '', 'post', ''],
            ['foo', 'pre', 'post', ''],

            ['http://', '', '', 'http']
        ];
    }

    /**
     * Provides successful validation data
     *
     * @return array
     */
    public function validateSuccessProvider()
    {
        return [
            ['http://www.example.com', '', '', ''],
            ['www.example.com', 'http://', '', ''],
            ['www.example.com', 'http://', 'bang', ''],
            ['http://www.example.com', '', 'bang', ''],

            ['foo', '', '', 'http'],
            ['http', '', '', 'http'],
            ['foo', 'pre', '', 'http'],
            ['foo', '', 'post', 'http'],
            ['foo', 'pre', 'post', 'http']
        ];
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
        $url = new Url(['prefix' => $prefix, 'postfix' => $postfix, 'autoscheme' => $autoscheme]);
        $url->validate($value);
    }

    /**
     * @dataProvider validateSuccessProvider
     */
    public function test_validate_success($value, $prefix, $postfix, $autoscheme)
    {
        $url = new Url(['prefix' => $prefix, 'postfix' => $postfix, 'autoscheme' => $autoscheme]);
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
