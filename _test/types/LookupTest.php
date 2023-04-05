<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\PageMeta;
use dokuwiki\plugin\struct\test\mock\AccessTable;
use dokuwiki\plugin\struct\test\mock\Lookup;
use dokuwiki\plugin\struct\test\StructTest;
use DOMWrap\Document;

/**
 * Testing the Dropdown Type
 *
 * @group plugin_struct
 * @group plugins
 */
class LookupTest extends StructTest
{

    protected function prepareLookup()
    {
        saveWikiText('title1', 'test', 'test');
        $pageMeta = new PageMeta('title1');
        $pageMeta->setTitle('This is a title');

        saveWikiText('title2', 'test', 'test');
        $pageMeta = new PageMeta('title2');
        $pageMeta->setTitle('This is a 2nd title');

        saveWikiText('title3', 'test', 'test');
        $pageMeta = new PageMeta('title3');
        $pageMeta->setTitle('Another Title');

        $this->loadSchemaJSON('pageschema', '', 0);
        $access = AccessTable::getGlobalAccess('pageschema');
        $access->saveData(
            [
                'singlepage' => 'title1',
                'multipage' => ['title1'],
                'singletitle' => 'title1',
                'multititle' => ['title1'],
            ]
        );
        $access = AccessTable::getGlobalAccess('pageschema');
        $access->saveData(
            [
                'singlepage' => 'title2',
                'multipage' => ['title2'],
                'singletitle' => 'title2',
                'multititle' => ['title2'],
            ]
        );
        $access = AccessTable::getGlobalAccess('pageschema');
        $access->saveData(
            [
                'singlepage' => 'title3',
                'multipage' => ['title3'],
                'singletitle' => 'title3',
                'multititle' => ['title3'],
            ]
        );
    }

    protected function prepareTranslation()
    {
        $this->loadSchemaJSON('translation', '', 0);
        $access = AccessTable::getGlobalAccess('translation');
        $access->saveData(
            [
                'en' => 'shoe',
                'de' => 'Schuh',
                'fr' => 'chaussure'
            ]
        );

        $access = AccessTable::getGlobalAccess('translation');
        $access->saveData(
            [
                'en' => 'dog',
                'de' => 'Hund',
                'fr' => 'chien'
            ]
        );

        $access = AccessTable::getGlobalAccess('translation');
        $access->saveData(
            [
                'en' => 'cat',
                'de' => 'Katze',
                'fr' => 'Chat'
            ]
        );
    }

    protected function preparePages()
    {
        $this->loadSchemaJSON('dropdowns');
        $this->saveData(
            'test1', 'dropdowns', [
            'drop1' => json_encode(['', 1]), 'drop2' => json_encode(['', 1]), 'drop3' => 'John'
        ], time()
        );
        $this->saveData(
            'test2', 'dropdowns', [
            'drop1' => json_encode(['', 2]), 'drop2' => json_encode(['', 2]), 'drop3' => 'Jane'
        ],
            time()
        );
        $this->saveData(
            'test3', 'dropdowns', [
            'drop1' => json_encode(['', 3]), 'drop2' => json_encode(['', 3]), 'drop3' => 'Tarzan'
        ],
            time()
        );
    }

    public function test_data()
    {
        $this->prepareLookup();
        $this->preparePages();

        $access = AccessTable::getPageAccess('dropdowns', 'test1', time());
        $data = $access->getData();

        $this->assertEquals('["[\\"\\",1]","[\\"title1\\",\\"This is a title\\"]"]', $data['drop1']->getValue());
        $this->assertEquals('["[\\"\\",1]","title1"]', $data['drop2']->getValue());

        $this->assertEquals('["",1]', $data['drop1']->getRawValue());
        $this->assertEquals('["",1]', $data['drop2']->getRawValue());

        $this->assertEquals('This is a title', $data['drop1']->getDisplayValue());
        $this->assertEquals('title1', $data['drop2']->getDisplayValue());

        $R = new \Doku_Renderer_xhtml();
        $data['drop1']->render($R, 'xhtml');

        $doc = new Document();
        $doc->html($R->doc);

        $this->assertEquals('This is a title', $doc->find('a')->text());
        $this->assertStringContainsString('title1', $doc->find('a')->attr('href'));

        $R = new \Doku_Renderer_xhtml();
        $data['drop2']->render($R, 'xhtml');

        $doc = new Document();
        $doc->html($R->doc);
        $this->assertEquals('title1', $doc->find('a')->text());
        $this->assertStringContainsString('title1', $doc->find('a')->attr('href'));
    }

    public function test_translation()
    {
        global $conf;
        $this->prepareTranslation();

        // lookup in english
        $dropdown = new Lookup(
            [
                'schema' => 'translation',
                'field' => '$LANG'
            ],
            'test',
            false,
            0
        );
        $expect = [
            '' => '',
            json_encode(['', 3]) => 'cat',
            json_encode(['', 2]) => 'dog',
            json_encode(['', 1]) => 'shoe',
        ];
        $this->assertEquals($expect, $dropdown->getOptions());

        // fallback to english
        $conf['lang'] = 'zh';
        $dropdown = new Lookup(
            [
                'schema' => 'translation',
                'field' => '$LANG'
            ],
            'test',
            false,
            0
        );
        $this->assertEquals($expect, $dropdown->getOptions());

        // german
        $conf['lang'] = 'de';
        $dropdown = new Lookup(
            [
                'schema' => 'translation',
                'field' => '$LANG'
            ],
            'test',
            false,
            0
        );
        $expect = [
            '' => '',
            json_encode(['', 2]) => 'Hund',
            json_encode(['', 3]) => 'Katze',
            json_encode(['', 1]) => 'Schuh',
        ];
        $this->assertEquals($expect, $dropdown->getOptions());

        // french
        $conf['lang'] = 'fr';
        $dropdown = new Lookup(
            [
                'schema' => 'translation',
                'field' => '$LANG'
            ],
            'test',
            false,
            0
        );
        $expect = [
            '' => '',
            json_encode(['', 2]) => 'chien',
            json_encode(['', 3]) => 'Chat',
            json_encode(['', 1]) => 'chaussure',
        ];
        $this->assertEquals($expect, $dropdown->getOptions());
    }

    public function test_getOptions()
    {
        $this->prepareLookup();

        // lookup with titles
        $dropdown = new Lookup(
            [
                'schema' => 'pageschema',
                'field' => 'singletitle'
            ],
            'test',
            false,
            0
        );
        $expect = [
            '' => '',
            json_encode(['', 3]) => 'Another Title',
            json_encode(['', 2]) => 'This is a 2nd title',
            json_encode(['', 1]) => 'This is a title',
        ];
        $this->assertEquals($expect, $dropdown->getOptions());

        // lookup with pages
        $dropdown = new Lookup(
            [
                'schema' => 'pageschema',
                'field' => 'singlepage'
            ],
            'test',
            false,
            0
        );
        $expect = [
            '' => '',
            json_encode(['', 1]) => 'title1',
            json_encode(['', 2]) => 'title2',
            json_encode(['', 3]) => 'title3',
        ];
        $this->assertEquals($expect, $dropdown->getOptions());

    }

}
