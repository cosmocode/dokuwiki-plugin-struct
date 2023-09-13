<?php

/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\StructException;

class syntax_plugin_struct_output extends SyntaxPlugin
{
    protected $hasBeenRendered = false;

    protected const XHTML_OPEN = '<div id="plugin__struct_output">';
    protected const XHTML_CLOSE = '</div>';

    /**
     * Regexp to check on which actions the struct data may be rendered
     */
    protected const WHITELIST_ACTIONS = '/^(show|export_.*)$/';

    /**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        return 155;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * We do not connect any pattern here, because the call to this plugin is not
     * triggered from syntax but our action component
     *
     * @asee action_plugin_struct_output
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
    }

    /**
     * Handle matches of the struct syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // this is never called
        return [];
    }

    /**
     * Render schema data
     *
     * Currently completely renderer agnostic
     *
     * @param string $format Renderer format
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        global $ACT;
        global $ID;
        global $INFO;
        global $REV;

        foreach (helper_plugin_struct::BLACKLIST_RENDERER as $blacklisted) {
            if ($renderer instanceof $blacklisted) {
                return true;
            }
        }
        if (!isset($INFO['id']) || ($ID != $INFO['id'])) return true;
        if (!$INFO['exists']) return true;
        if ($this->hasBeenRendered) return true;
        if (!preg_match(self::WHITELIST_ACTIONS, act_clean($ACT))) return true;

        // do not render the output twice on the same page, e.g. when another page has been included
        $this->hasBeenRendered = true;
        try {
            $assignments = Assignments::getInstance();
        } catch (StructException $e) {
            return false;
        }
        $tables = $assignments->getPageAssignments($ID);
        if (!$tables) return true;

        if ($format == 'xhtml') $renderer->doc .= self::XHTML_OPEN;

        $hasdata = false;
        foreach ($tables as $table) {
            try {
                $schemadata = AccessTable::getPageAccess($table, $ID, (int)$REV);
            } catch (StructException $ignored) {
                continue; // no such schema at this revision
            }

            $rendercontext = [
                'renderer' => $renderer,
                'format' => $format,
                'meta' => p_get_metadata($ID),
                'schemadata' => $schemadata,
                'hasdata' => &$hasdata
            ];

            $event = new Event(
                'PLUGIN_STRUCT_RENDER_SCHEMA_DATA',
                $rendercontext
            );
            $event->trigger([$this, 'renderSchemaData']);
        }

        if ($format == 'xhtml') $renderer->doc .= self::XHTML_CLOSE;

        // if no data has been output, remove empty wrapper again
        if ($format == 'xhtml' && !$hasdata) {
            $renderer->doc = substr($renderer->doc, 0, -1 * strlen(self::XHTML_OPEN . self::XHTML_CLOSE));
        }

        return true;
    }

    /**
     * Default schema data rendering (simple table view)
     *
     * @param array The render context including renderer and data
     */
    public function renderSchemaData($rendercontext)
    {
        $schemadata = $rendercontext['schemadata'];
        $renderer = $rendercontext['renderer'];
        $format = $rendercontext['format'];

        $schemadata->optionSkipEmpty(true);
        $data = $schemadata->getData();
        if (!count($data))
            return;

        $rendercontext['hasdata'] = true;

        if ($format == 'xhtml') {
            $renderer->doc .= '<div class="struct_output_' . $schemadata->getSchema()->getTable() . '">';
        }

        $renderer->table_open();
        $renderer->tablethead_open();
        $renderer->tablerow_open();
        $renderer->tableheader_open(2);
        $renderer->cdata($schemadata->getSchema()->getTranslatedLabel());
        $renderer->tableheader_close();
        $renderer->tablerow_close();
        $renderer->tablethead_close();

        $renderer->tabletbody_open();
        foreach ($data as $field) {
            $renderer->tablerow_open();
            $renderer->tableheader_open();
            $renderer->cdata($field->getColumn()->getTranslatedLabel());
            $renderer->tableheader_close();
            $renderer->tablecell_open();
            if ($format == 'xhtml') {
                $renderer->doc = substr($renderer->doc, 0, -1) .
                    ' data-struct="' . hsc($field->getColumn()->getFullQualifiedLabel()) .
                    '">';
            }
            $field->render($renderer, $format);
            $renderer->tablecell_close();
            $renderer->tablerow_close();
        }
        $renderer->tabletbody_close();
        $renderer->table_close();

        if ($format == 'xhtml') {
            $renderer->doc .= '</div>';
        }
    }
}

// vim:ts=4:sw=4:et:
