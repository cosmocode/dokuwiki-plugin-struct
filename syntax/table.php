<?php

/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AggregationTable;
use dokuwiki\plugin\struct\meta\ConfigParser;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;

class syntax_plugin_struct_table extends DokuWiki_Syntax_Plugin
{
    /** @var string which class to use for output */
    protected $tableclass = AggregationTable::class;

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
        $this->Lexer->addSpecialPattern('----+ *struct table *-+\n.*?\n----+', $mode, 'plugin_struct_table');
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
     * Render xhtml output or metadata
     *
     * @param string $format Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $config The parsed config data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($format, Doku_Renderer $renderer, $config)
    {
        global $INFO;
        global $conf;

        if (!$config) return false;
        $config = $this->addTypeFilter($config); // add type specific filters

        // always use the main page's ID @todo might make sense as utility method somewhere
        if ($INFO !== null) {
            $mainId = $INFO['id'];
        } else {
            $mainId = getID();
        }

        try {
            $search = new SearchConfig($config);
            if ($format === 'struct_csv') {
                // no pagination in export
                $search->setLimit(0);
                $search->setOffset(0);
            }

            /** @var AggregationTable $table */
            $table = new $this->tableclass($mainId, $format, $renderer, $search);
            $table->render();

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

    /**
     * Filter based on primary key columns, applicable in child classes
     *
     * @param array $config
     * @return array
     */
    protected function addTypeFilter($config)
    {
        return $config;
    }
}
