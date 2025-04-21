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

/**
 * Class action_plugin_struct_entry
 *
 * Handles reverting to old data via revert action
 */
class action_plugin_struct_revert extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        // ensure a page revision is created when struct data changes:
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handlePagesaveBefore');
        // save struct data after page has been saved:
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'AFTER', $this, 'handlePagesaveAfter');
    }

    /**
     * Check if the page has to be changed
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handlePagesaveBefore(Event $event, $param)
    {
        if ($event->data['contentChanged']) return false; // will be saved for page changes already
        global $ACT;
        global $REV;
        if ($ACT != 'revert' || !$REV) return false;

        // force changes for revert if there are assignments
        $assignments = Assignments::getInstance();
        $tosave = $assignments->getPageAssignments($event->data['id']);
        if ($tosave !== []) {
            $event->data['contentChanged'] = true; // save for data changes
        }

        return true;
    }

    /**
     * Save the data, by loading it from the old revision and storing it as a new revision
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handlePagesaveAfter(Event $event, $param)
    {
        global $ACT;
        global $REV;
        if ($ACT != 'revert' || !$REV) return false;

        $assignments = Assignments::getInstance();

        //  we load the data to restore from DB:
        $tosave = $assignments->getPageAssignments($event->data['id']);
        foreach ($tosave as $table) {
            $accessOld = AccessTable::getPageAccess($table, $event->data['id'], $REV);
            $accessNew = AccessTable::getPageAccess($table, $event->data['id'], $event->data['newRevision']);
            $accessNew->saveData($accessOld->getDataArray());

            // make sure this schema is assigned
            $assignments->assignPageSchema($event->data['id'], $table);
        }
        return true;
    }
}
