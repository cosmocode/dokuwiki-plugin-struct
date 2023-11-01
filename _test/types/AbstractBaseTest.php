<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\test\mock\BaseType;
use dokuwiki\plugin\struct\test\StructTest;

/**
 * @group plugin_struct
 * @group plugins
 */
class AbstractBaseTest extends StructTest
{

    protected $preset = [
        'label' => [
            'de' => 'german label',
            'zh' => 'chinese label' // always stripped
        ],
        'hint' => [
            'en' => 'english hint',
            'de' => 'german hint',
            'zh' => 'chinese hint' // always stripped
        ]
    ];

    /**
     * Translation Init: empty config, no translation plugin
     */
    public function test_trans_empty_noplugin()
    {
        global $conf;
        $conf['lang'] = 'en';

        $type = new BaseType(null, 'A Label');
        $this->assertEquals(
            array(
                'label' => array(
                    'en' => ''
                ),
                'hint' => array(
                    'en' => ''
                ),
                'visibility' => array('inpage' => true, 'ineditor' => true)
            ),
            $type->getConfig()
        );
        $this->assertEquals('A Label', $type->getTranslatedLabel());
        $this->assertEquals('', $type->getTranslatedHint());
    }

    /**
     * Translation Init: preset config, no translation plugin
     */
    public function test_trans_preset_noplugin()
    {
        global $conf;
        $conf['lang'] = 'en';

        $type = new BaseType($this->preset, 'A Label');
        $this->assertEquals(
            array(
                'label' => array(
                    'en' => ''
                ),
                'hint' => array(
                    'en' => 'english hint'
                ),
                'visibility' => array('inpage' => true, 'ineditor' => true)
            ),
            $type->getConfig()
        );
        $this->assertEquals('A Label', $type->getTranslatedLabel());
        $this->assertEquals('english hint', $type->getTranslatedHint());
    }

    /**
     * Translation Init: empty config, translation plugin
     */
    public function test_trans_empty_plugin()
    {
        global $conf;
        $conf['lang'] = 'en';
        $conf['plugin']['translation']['translations'] = 'fr tr it de';

        $type = new BaseType(null, 'A Label');
        $this->assertEquals(
            [
                'label' => [
                    'en' => '',
                    'fr' => '',
                    'tr' => '',
                    'it' => '',
                    'de' => '',
                ],
                'hint' => [
                    'en' => '',
                    'fr' => '',
                    'tr' => '',
                    'it' => '',
                    'de' => '',
                ],
                'visibility' => ['inpage' => true, 'ineditor' => true]
            ],
            $type->getConfig()
        );
        $this->assertEquals('A Label', $type->getTranslatedLabel());
        $this->assertEquals('', $type->getTranslatedHint());
        $conf['lang'] = 'de';
        $this->assertEquals('A Label', $type->getTranslatedLabel());
        $this->assertEquals('', $type->getTranslatedHint());
        $conf['lang'] = 'zh';
        $this->assertEquals('A Label', $type->getTranslatedLabel());
        $this->assertEquals('', $type->getTranslatedHint());
        $conf['lang'] = 'en';
    }

    /**
     * Translation Init: preset config, translation plugin
     */
    public function test_trans_preset_plugin()
    {
        global $conf;
        $conf['lang'] = 'en';
        $conf['plugin']['translation']['translations'] = 'fr tr it de';

        $type = new BaseType($this->preset, 'A Label');
        $this->assertEquals(
            [
                'label' => [
                    'en' => '',
                    'fr' => '',
                    'tr' => '',
                    'it' => '',
                    'de' => 'german label',
                ],
                'hint' => [
                    'en' => 'english hint',
                    'fr' => '',
                    'tr' => '',
                    'it' => '',
                    'de' => 'german hint',
                ],
                'visibility' => ['inpage' => true, 'ineditor' => true]
            ],
            $type->getConfig()
        );
        $this->assertEquals('A Label', $type->getTranslatedLabel());
        $this->assertEquals('english hint', $type->getTranslatedHint());
        $conf['lang'] = 'de';
        $this->assertEquals('german label', $type->getTranslatedLabel());
        $this->assertEquals('german hint', $type->getTranslatedHint());
        $conf['lang'] = 'zh';
        $this->assertEquals('A Label', $type->getTranslatedLabel()); # falls back to column
        $this->assertEquals('english hint', $type->getTranslatedHint());  # falls back to english
        $conf['lang'] = 'fr';
        $this->assertEquals('A Label', $type->getTranslatedLabel()); # falls back to column
        $this->assertEquals('english hint', $type->getTranslatedHint());  # falls back to english
        $conf['lang'] = 'en';
    }
}
