<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\struct\meta\Schema;

/**
 * Class action_plugin_struct_output
 *
 * This action component handles the automatic output of all schema data that has been assigned
 * to the current page by appending the appropriate instruction to the handler calls.
 *
 * The real output creation is done within the syntax component
 * @see syntax_plugin_struct_output
 */
class action_plugin_struct_output extends ActionPlugin
{
    protected const DW2PDF_PLACEHOLDER_PREFIX = 'PLUGIN_STRUCT';

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'handleOutput');
        $controller->register_hook('PLUGIN_DW2PDF_REPLACE', 'BEFORE', $this, 'replaceDw2pdf');
        $controller->register_hook('PLUGIN_DW2PDF_REPLACE', 'AFTER', $this, 'cleanupDw2pdf');
    }

    /**
     * Appends the instruction to render our syntax output component to each page
     * after the first found headline or the very begining if no headline was found
     *
     * @param Event $event
     * @param $param
     */
    public function handleOutput(Event $event, $param)
    {
        global $ID;
        if (!$ID) return;
        if (!page_exists($ID)) return;

        $pos = 0;
        $ins = -1;

        // display struct data at the bottom?
        if ($this->getConf('bottomoutput')) {
            $ins = count($event->data->calls);
        } elseif (!$this->getConf('topoutput')) {
            foreach ($event->data->calls as $num => $call) {
                // try to find the first header
                if ($call[0] == 'header') {
                    $pos = $call[2];
                    $ins = $num;
                    break;
                }

                // abort when after we looked at the first 150 bytes
                if (isset($call[3]) && $call[3] > 150) {
                    break;
                }
            }
        }

        // insert our own call after the found position
        array_splice(
            $event->data->calls,
            $ins + 1,
            0,
            [
                [
                    'plugin',
                    [
                        'struct_output', ['pos' => $pos], DOKU_LEXER_SPECIAL, ''
                    ],
                    $pos
                ]
            ]
        );
    }

    /**
     * If the page has a schema assigned, add its struct data
     * to dw2pdf's template replacements
     *
     * @param Event $event
     * @param $param
     */
    public function replaceDw2pdf(Event $event, $param)
    {
        if (!$event->data['id'] || !page_exists($event->data['id'])) return;

        global $REV;
        $rev = $REV ?: time();

        /** @var helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        $data = $helper->getData($event->data['id'], null, $rev);

        if (!$data) return;

        foreach ($data as $schema => $fields) {
            $schemaObject = new Schema($schema);
            foreach ($fields as $field => $value) {
                // format fields
                $col = $schemaObject->findColumn($field);
                if (is_a($col->getType(), '\dokuwiki\plugin\struct\types\Date')) {
                    $format = $col->getType()->getConfig()['format'];
                    $value = date($format, strtotime($value));
                }

                $placeholder = sprintf('@%s_%s_%s@', self::DW2PDF_PLACEHOLDER_PREFIX, $schema, $field);
                $event->data['replace'][$placeholder] = is_array($value) ? implode(', ', $value) : $value;
            }
        }
    }

    /**
     * Remove struct placeholders still present after replacement.
     * Requested data was not found.
     *
     * @param Event $event
     * @param $param
     */
    public function cleanupDw2pdf(Event $event, $param)
    {
        $pattern = '~@' . self::DW2PDF_PLACEHOLDER_PREFIX . '_[^@]+?@~';
        $event->data['content'] = preg_replace($pattern, '', $event->data['content']);
    }
}

// vim:ts=4:sw=4:et:
