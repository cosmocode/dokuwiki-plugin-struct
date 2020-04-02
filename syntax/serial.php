<?php
/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große, Anna Dabrowska <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AccessTableData;

class syntax_plugin_struct_serial extends syntax_plugin_struct_lookup {

    /**
     * Connect serial pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *struct serial *-+\n.*?\n----+', $mode, 'plugin_struct_serial');
    }

    /**
     * Filter based on primary key columns
     *
     * @param array $config
     * @return array
     */
    protected function addTypeFilter($config)
    {
        $config['filter'][] = ['%rowid%', '!=', (string)AccessTableData::DEFAULT_PAGE_RID, 'AND'];
        $config['filter'][] = ['%pageid%', '!=', '', 'AND'];
        $config['withpid'] = 1; // flag for the editor to distinguish data types
        return $config;
    }
}