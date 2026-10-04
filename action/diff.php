<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\StructException;

class action_plugin_struct_diff extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handleDiffload');
    }

    /**
     * Add structured data to the diff
     *
     * This is done by adding pseudo syntax to the page source when it is loaded in diff context
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleDiffload(Event $event, $param)
    {
        global $ACT;
        global $INFO;
        if ($ACT != 'diff') return;
        $id = $event->data[2];
        if (!blank($event->data[1])) {
            $id = $event->data[1] . ':' . $id;
        }
        $rev = $event->data[3] ?: time();
        if ($INFO['id'] != $id) return;

        $assignments = Assignments::getInstance();
        $tables = $assignments->getPageAssignments($id);
        if (!$tables) return;

        $event->result .= "\n---- struct data ----\n";
        foreach ($tables as $table) {
            try {
                $schemadata = AccessTable::getPageAccess($table, $id, $rev);
            } catch (StructException $ignored) {
                continue; // no such schema at this revision
            }
            $event->result .= $schemadata->getDataPseudoSyntax();
        }
        $event->result .= "----\n";
    }
}

// vim:ts=4:sw=4:et:
