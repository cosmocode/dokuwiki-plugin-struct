<?php

/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\StructException;

class syntax_plugin_struct_output extends DokuWiki_Syntax_Plugin
{

    protected $hasBeenRendered = false;

    const XHTML_OPEN = '<div id="plugin__struct_output">';
    const XHTML_CLOSE = '</div>';

    /**
     * Class names of renderers which should NOT render struct data.
     * All descendants are also blacklisted.
     */
    const BLACKLIST_RENDERER = array('Doku_Renderer_metadata');

    /**
     * Regexp to check on which actions the struct data may be rendered
     */
    const WHITELIST_ACTIONS = '/^(show|export_.*)$/';

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
        return array();
    }

    /**
     * Render schema data
     *
     * Currently completely renderer agnostic
     *
     * @param string $mode Renderer mode
     * @param Doku_Renderer $R The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $R, $data)
    {
        global $ACT;
        global $ID;
        global $INFO;
        global $REV;

        foreach (self::BLACKLIST_RENDERER as $blacklisted) {
            if ($R instanceof $blacklisted) {
                return true;
            }
        }
        if ($ID != $INFO['id']) return true;
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

        if ($mode == 'xhtml') $R->doc .= self::XHTML_OPEN;

        $hasdata = false;
        foreach ($tables as $table) {
            try {
                $schemadata = AccessTable::getPageAccess($table, $ID, (int)$REV);
            } catch (StructException $ignored) {
                continue; // no such schema at this revision
            }
            $schemadata->optionSkipEmpty(true);
            $data = $schemadata->getData();
            if (!count($data)) continue;
            $hasdata = true;

            $R->table_open();

            $R->tablethead_open();
            $R->tablerow_open();
            $R->tableheader_open(2);
            $R->cdata($schemadata->getSchema()->getTranslatedLabel());
            $R->tableheader_close();
            $R->tablerow_close();
            $R->tablethead_close();

            $R->tabletbody_open();
            foreach ($data as $field) {
                $R->tablerow_open();
                $R->tableheader_open();
                $R->cdata($field->getColumn()->getTranslatedLabel());
                $R->tableheader_close();
                $R->tablecell_open();
                if ($mode == 'xhtml') {
                    $R->doc = substr($R->doc, 0, -1) .
                        ' data-struct="' . hsc($field->getColumn()->getFullQualifiedLabel()) .
                        '">';
                }
                $field->render($R, $mode);
                $R->tablecell_close();
                $R->tablerow_close();
            }
            $R->tabletbody_close();
            $R->table_close();
        }

        if ($mode == 'xhtml') $R->doc .= self::XHTML_CLOSE;

        // if no data has been output, remove empty wrapper again
        if ($mode == 'xhtml' && !$hasdata) {
            $R->doc = substr($R->doc, 0, -1 * strlen(self::XHTML_OPEN . self::XHTML_CLOSE));
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
