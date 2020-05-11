<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;

/**
 * Inject struct data into indexed pages and search result snippets
 */
class action_plugin_struct_search extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, 'handleIndexing');
        $controller->register_hook('FULLTEXT_SNIPPET_CREATE', 'BEFORE', $this, 'handleSnippets');
    }

    /**
     * Adds the structured data to the page body to be indexed
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleIndexing(Doku_Event $event, $param)
    {
        $id = $event->data['page'];
        $assignments = Assignments::getInstance();
        $tables = $assignments->getPageAssignments($id);
        if (!$tables) return;

        foreach ($tables as $table) {
            $schemadata = AccessTable::getPageAccess($table, $id);
            $event->data['body'] .= $schemadata->getDataPseudoSyntax();
        }
    }

    /**
     * Adds the structured data to the page body to be snippeted
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleSnippets(Doku_Event $event, $param)
    {
        $id = $event->data['id'];
        $assignments = Assignments::getInstance();
        $tables = $assignments->getPageAssignments($id);
        if (!$tables) return;

        foreach ($tables as $table) {
            $schemadata = AccessTable::getPageAccess($table, $id);
            $event->data['text'] .= $schemadata->getDataPseudoSyntax();
        }
    }
}

// vim:ts=4:sw=4:et:
