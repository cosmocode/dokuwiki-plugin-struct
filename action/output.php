<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

/**
 * Class action_plugin_struct_output
 *
 * This action component handles the automatic output of all schema data that has been assigned
 * to the current page by appending the appropriate instruction to the handler calls.
 *
 * The real output creation is done within the syntax component
 * @see syntax_plugin_struct_output
 */
class action_plugin_struct_output extends DokuWiki_Action_Plugin
{

    const DW2PDF_PLACEHOLDER_PREFIX = 'PLUGIN_STRUCT';

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'handleOutput');
        $controller->register_hook('PLUGIN_DW2PDF_REPLACE', 'BEFORE', $this, 'replaceDw2pdf');
        $controller->register_hook('PLUGIN_DW2PDF_REPLACE', 'AFTER', $this, 'cleanupDw2pdf');
    }

    /**
     * Appends the instruction to render our syntax output component to each page
     * after the first found headline or the very begining if no headline was found
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handleOutput(Doku_Event $event, $param)
    {
        global $ID;
        if (!page_exists($ID)) return;
        $ins = -1;
        $pos = 0;
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

        // insert our own call after the found position
        array_splice(
            $event->data->calls,
            $ins + 1,
            0,
            array(
                array(
                    'plugin',
                    array(
                        'struct_output', array('pos' => $pos), DOKU_LEXER_SPECIAL, ''
                    ),
                    $pos
                )
            )
        );
    }

    /**
     * If the page has a schema assigned, add its struct data
     * to dw2pdf's template replacements
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function replaceDw2pdf(Doku_Event $event, $param)
    {
        if (!$event->data['id'] || !page_exists($event->data['id'])) return;

        global $REV;
        $rev = $REV ?: time();

        /** @var helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        $data = $helper->getData($event->data['id'], null, $rev);

        if (!$data) return;

        foreach ($data as $schema => $fields) {
            foreach ($fields as $field => $value) {
                $placeholder = sprintf('@%s_%s_%s@', self::DW2PDF_PLACEHOLDER_PREFIX, $schema, $field);
                $event->data['replace'][$placeholder] = is_array($value) ? implode(', ', $value) : $value;
            }
        }
    }

    /**
     * Remove struct placeholders still present after replacement.
     * Requested data was not found.
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function cleanupDw2pdf(Doku_Event $event, $param)
    {
        $pattern = '~@' . self::DW2PDF_PLACEHOLDER_PREFIX . '_[^@]+?@~';
        $event->data['content'] = preg_replace($pattern, '', $event->data['content']);
    }
}

// vim:ts=4:sw=4:et:
