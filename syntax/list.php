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
}
