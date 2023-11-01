<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\Media;
use DOMWrap\Document;

/**
 * Testing the Media Type
 *
 * @group plugin_struct
 * @group plugins
 */
class MediaTest extends StructTest
{

    /**
     * Provides failing validation data
     *
     * @return array
     */
    public function validateFailProvider()
    {
        return [
            ['image/jpeg, image/png', 'foo.gif'],
            ['image/jpeg, image/png', 'http://www.example.com/foo.gif'],
            ['application/octet-stream', 'hey:joe.jpeg'],
            ['application/octet-stream', 'http://www.example.com/hey:joe.jpeg'],
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
            ['', 'foo.png'],
            ['', 'http://www.example.com/foo.png'],
            ['image/jpeg, image/png', 'foo.png'],
            ['image/jpeg, image/png', 'http://www.example.com/foo.png'],
            ['image/jpeg, image/png', 'http://www.example.com/dynamic?.png'],
            ['application/octet-stream', 'hey:joe.exe'],
            ['application/octet-stream', 'http://www.example.com/hey:joe.exe'],

        ];
    }

    /**
     * @dataProvider validateFailProvider
     */
    public function test_validate_fail($mime, $value)
    {
        $this->expectException(ValidationException::class);
        $integer = new Media(['mime' => $mime]);
        $integer->validate($value);
    }

    /**
     * @dataProvider validateSuccessProvider
     */
    public function test_validate_success($mime, $value)
    {
        $integer = new Media(['mime' => $mime]);
        $integer->validate($value);
        $this->assertTrue(true); // we simply check that no exceptions are thrown
    }

    public function test_render_page_img()
    {
        $R = new \Doku_Renderer_xhtml();

        $media = new Media(['width' => 150, 'height' => 160, 'agg_width' => 180, 'agg_height' => 190]);
        $media->renderValue('foo.png', $R, 'xhtml');
        $doc = new Document();
        $doc->html($R->doc);

        $a = $doc->find('a');
        $img = $doc->find('img');

        $this->assertStringContainsString('fetch.php', $a->attr('href')); // direct link goes to fetch
        $this->assertEquals('lightbox', $a->attr('rel')); // lightbox single mode
        $this->assertStringContainsString('w=150', $img->attr('src')); // fetch param
        $this->assertEquals(150, $img->attr('width')); // img param
        $this->assertStringContainsString('h=160', $img->attr('src')); // fetch param
        $this->assertEquals(160, $img->attr('height')); // img param
    }

    public function test_render_aggregation_img()
    {
        $R = new \Doku_Renderer_xhtml();
        $R->info['struct_table_hash'] = 'HASH';

        $media = new Media(['width' => 150, 'height' => 160, 'agg_width' => 180, 'agg_height' => 190]);
        $media->renderValue('foo.png', $R, 'xhtml');
        $pq = new Document();
        $pq->html($R->doc);

        $a = $pq->find('a');
        $img = $pq->find('img');

        $this->assertStringContainsString('fetch.php', $a->attr('href')); // direct link goes to fetch
        $this->assertEquals('lightbox[gal-HASH]', $a->attr('rel')); // lightbox single mode
        $this->assertStringContainsString('w=180', $img->attr('src')); // fetch param
        $this->assertEquals(180, $img->attr('width')); // img param
        $this->assertStringContainsString('h=190', $img->attr('src')); // fetch param
        $this->assertEquals(190, $img->attr('height')); // img param
    }

    public function test_render_aggregation_pdf()
    {
        $R = new \Doku_Renderer_xhtml();

        $media = new Media(['width' => 150, 'height' => 160, 'agg_width' => 180, 'agg_height' => 190, 'mime' => '']);
        $media->renderValue('foo.pdf', $R, 'xhtml');
        $doc = new Document();
        $doc->html($R->doc);

        $a = $doc->find('a');
        $img = $doc->find('img');

        $this->assertStringContainsString('fetch.php', $a->attr('href')); // direct link goes to fetch
        $this->assertTrue($a->hasClass('mediafile')); // it's a media link
        $this->assertEquals('', $a->attr('rel')); // no lightbox
        $this->assertEquals(0, $img->count()); // no image
        $this->assertEquals('foo.pdf', $a->text()); // name is link name
    }

    public function test_render_aggregation_video()
    {
        $R = new \Doku_Renderer_xhtml();

        // local video requires an existing file to be rendered. we fake one
        $fake = mediaFN('foo.mp4');
        touch($fake);

        $media = new Media(['width' => 150, 'height' => 160, 'agg_width' => 180, 'agg_height' => 190, 'mime' => '']);
        $media->renderValue('foo.mp4', $R, 'xhtml');

        $doc = new Document();
        $doc->html($R->doc);

        $a = $doc->find('a');
        $vid = $doc->find('video');
        $src = $doc->find('source');

        $this->assertStringContainsString('fetch.php', $a->attr('href')); // direct link goes to fetch
        $this->assertStringContainsString('fetch.php', $src->attr('src')); // direct link goes to fetch
        $this->assertEquals(150, $vid->attr('width')); // video param
        $this->assertEquals(160, $vid->attr('height')); // video param
    }

}
