<?php

/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AggregationList;
use dokuwiki\plugin\struct\meta\ConfigParser;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;

class syntax_plugin_struct_list extends syntax_plugin_struct_table
{
    /** @inheritdoc */
    protected $tableclass = AggregationList::class;

    /** @inheritdoc */
    protected $illegalOptions = ['dynfilters', 'summarize', 'rownumbers', 'widths', 'summary'];

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *struct list *-+\n.*?\n----+', $mode, 'plugin_struct_list');
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function Xrender($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode != 'xhtml') return false;
        if (!$data) return false;
        global $INFO;
        global $conf;

        try {
            $search = new SearchConfig($data);

            /** @var AggregationList $list */
            $list = new $this->tableclass($INFO['id'], $mode, $renderer, $search);
            $list->render();

            if ($mode == 'metadata') {
                /** @var Doku_Renderer_metadata $renderer */
                $renderer->meta['plugin']['struct']['hasaggregation'] = $search->getCacheFlag();
            }
        } catch (StructException $e) {
            msg($e->getMessage(), -1, $e->getLine(), $e->getFile());
            if ($conf['allowdebug']) msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
        }

        return true;
    }
}
