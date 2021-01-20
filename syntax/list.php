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

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_struct_list extends DokuWiki_Syntax_Plugin
{

    /** @var string which class to use for output */
    protected $tableclass = AggregationList::class;

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
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *struct list *-+\n.*?\n----+', $mode, 'plugin_struct_list');
    }

    /**
     * Handle matches of the struct syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $conf;

        $lines = explode("\n", $match);
        array_shift($lines);
        array_pop($lines);

        try {
            $parser = new ConfigParser($lines);
            $config = $parser->getConfig();
            return $config;
        } catch (StructException $e) {
            msg($e->getMessage(), -1, $e->getLine(), $e->getFile());
            if ($conf['allowdebug']) msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
            return null;
        }
    }

    /**
     * Checks for options that do not work in a list aggregation
     *
     * @param array $config
     */
    protected function checkForInvalidOptions($config)
    {
        $illegal = ['dynfilters', 'summarize', 'rownumbers', 'widths', 'summary'];
        foreach ($illegal as $illegalOption)
        if (!empty($config[$illegalOption])) {
            throw new StructException('illegal option', $illegalOption);
        }
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode != 'xhtml') return false;
        if (!$data) return false;
        global $INFO;
        global $conf;

        try {
            $this->checkForInvalidOptions($data);
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

// vim:ts=4:sw=4:et:
