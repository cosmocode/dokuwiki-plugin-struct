<?php

/**
 * DokuWiki Plugin struct (Filter Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Anna Dabrowska <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AggregationFilter;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;

class syntax_plugin_struct_filter extends syntax_plugin_struct_table
{
    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *struct filter *-+\n.*?\n----+', $mode, 'plugin_struct_filter');
    }

    /**
     * @inheritDoc
     */
    public function render($format, Doku_Renderer $renderer, $config)
    {
        if ($format != 'xhtml') return false;
        if (!$config) return false;

        global $conf;
        global $INFO;

        try {
            $search = new SearchConfig($config, false);
            $filter = new AggregationFilter($INFO['id'], $format, $renderer, $search);

            $filter->startScope();
            $filter->render();
            $filter->finishScope();

            if ($format === 'metadata') {
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
