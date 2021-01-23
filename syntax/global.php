<?php

/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AggregationEditorTable;

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_struct_global extends syntax_plugin_struct_table
{

    /** @var string which class to use for output */
    protected $tableclass = AggregationEditorTable::class;

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *struct global *-+\n.*?\n----+', $mode, 'plugin_struct_global');

        // deprecated:
        $this->Lexer->addSpecialPattern('----+ *struct lookup *-+\n.*?\n----+', $mode, 'plugin_struct_global');
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
        // usual parsing
        $config = parent::handle($match, $state, $pos, $handler);
        if (is_null($config)) return null;

        $config = $this->addTypeFilter($config);

        // adjust some things for the lookup editor
        $config['cols'] = array('*'); // always select all columns
        if (isset($config['rownumbers'])) unset($config['rownumbers']); // this annoying to update dynamically
        return $config;
    }

    /**
     * Filter based on primary key columns
     *
     * @param array $config
     * @return array
     */
    protected function addTypeFilter($config)
    {
        $config['filter'][] = ['%rowid%', '!=', (string)\dokuwiki\plugin\struct\meta\AccessTablePage::DEFAULT_PAGE_RID, 'AND'];
        $config['filter'][] = ['%pageid%', '=*', '^(?![\s\S])', 'AND'];
        $config['withpid'] = 0; // flag for the editor to distinguish data types
        return $config;
    }
}
