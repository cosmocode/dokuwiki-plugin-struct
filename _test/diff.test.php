<?php

namespace dokuwiki\plugin\struct\test;

/**
 * Tests for the diff-view of the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 * @covers action_plugin_struct_diff
 *
 *
 */
class diff_struct_test extends StructTest
{

    public function setUp(): void
    {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
    }

    public function test_diff()
    {
        $page = 'test_save_page_without_new_text';
        $assignment = mock\Assignments::getInstance();
        $schema = 'schema1';
        $assignment->addPattern($page, $schema);
        $wikitext = 'teststring';

        // first save;
        $request = new \TestRequest();
        $structData = array(
            $schema => array(
                'first' => 'foo',
                'second' => 'bar, baz',
                'third' => 'foobar',
                'fourth' => '42'
            )
        );
        $request->setPost('struct_schema_data', $structData);
        $request->setPost('wikitext', $wikitext);
        $request->setPost('summary', 'content and struct data saved');
        $request->post(array('id' => $page, 'do' => 'save'), '/doku.php');

        $this->waitForTick(true);

        // second save - only struct data
        $request = new \TestRequest();
        $structData = array(
            $schema => array(
                'first' => 'foo',
                'second' => 'bar2, baz2',
                'third' => 'foobar2',
                'fourth' => '42'
            )
        );
        $request->setPost('struct_schema_data', $structData);
        $request->setPost('wikitext', $wikitext);
        $request->setPost('summary', '2nd revision');
        $request->post(array('id' => $page, 'do' => 'save'), '/doku.php');

        // diff
        $request = new \TestRequest();
        $response = $request->post(array('id' => $page, 'do' => 'diff'), '/doku.php');

        $pq = $response->queryHTML('table.diff_sidebyside');
        $this->assertEquals(1, $pq->length);

        $added = $pq->find('td.diff-addedline');
        $deleted = $pq->find('td.diff-deletedline');

        $this->assertEquals(2, $added->length);
        $this->assertEquals(2, $deleted->length);

        $this->assertStringContainsString('bar', $deleted->eq(0)->html());
        $this->assertStringContainsString('baz', $deleted->eq(0)->html());
        $this->assertStringContainsString('bar2', $added->eq(0)->html());
        $this->assertStringContainsString('baz2', $added->eq(0)->html());

        $this->assertStringContainsString('foobar', $deleted->eq(1)->html());
        $this->assertStringContainsString('foobar2', $added->eq(1)->html());
    }

}
