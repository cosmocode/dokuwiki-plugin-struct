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
use dokuwiki\plugin\struct\meta\AccessDataValidator;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;

/**
 * Class action_plugin_struct_entry
 *
 * Handles the entry process of struct data with type "page"
 */
class action_plugin_struct_entry extends ActionPlugin
{
    /**
     * @var string The form name we use to transfer schema data
     */
    protected static $VAR = 'struct_schema_data';

    /** @var helper_plugin_sqlite */
    protected $sqlite;

    /** @var  bool has the data been validated correctly? */
    protected $validated;

    /** @var  AccessDataValidator[] these schemas are validated and have changed data and need to be saved */
    protected $tosave;

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        // validate data on preview and save;
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleValidation');
        // ensure a page revision is created when struct data changes:
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handlePagesaveBefore');
        // save struct data after page has been saved:
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'AFTER', $this, 'handlePagesaveAfter');
    }

    /**
     * Clean up and validate the input data
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleValidation(Event $event, $param)
    {
        global $ID, $INPUT;
        $act = act_clean($event->data);
        if (!in_array($act, ['save', 'preview'])) return false;
        $this->tosave = [];

        // run the validation for each assignded schema
        $valid = AccessDataValidator::validateDataForPage($INPUT->arr(self::$VAR), $ID, $errors);
        if ($valid === false) {
            $this->validated = false;
            foreach ($errors as $error) {
                msg(hsc($error), -1);
            }
        } else {
            $this->validated = true;
            $this->tosave = $valid;
        }

        // FIXME we used to set the cleaned data as new input data. this caused #140
        // could we just not do that, and keep the cleaning to saving only? and fix that bug this way?

        // did validation go through? otherwise abort saving
        if (!$this->validated && $act == 'save') {
            $event->data = 'edit';
        }

        return true;
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
        if ($event->data['contentChanged']) return false; // will be saved for page changes
        global $ACT;
        if ($ACT == 'revert') return false; // this is handled in revert.php

        if ((is_array($this->tosave) && count($this->tosave)) || isset($GLOBALS['struct_plugin_force_page_save'])) {
            if (trim($event->data['newContent']) === '') {
                // this happens when a new page is tried to be created with only struct data
                msg($this->getLang('emptypage'), -1);
            } else {
                $event->data['contentChanged'] = true; // save for data changes

                // add a summary
                if (empty($event->data['summary'])) {
                    $event->data['summary'] = $this->getLang('summary');
                }
            }
        }

        return true;
    }

    /**
     * Save the data
     *
     * When this is called, INPUT data has been validated already.
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handlePagesaveAfter(Event $event, $param)
    {
        global $ACT;
        if ($ACT == 'revert') return false; // handled in revert

        $assignments = Assignments::getInstance();
        if ($event->data['changeType'] == DOKU_CHANGE_TYPE_DELETE && empty($GLOBALS['PLUGIN_MOVE_WORKING'])) {
            // clear all data on delete unless it's a move operation
            $tables = $assignments->getPageAssignments($event->data['id']);
            foreach ($tables as $table) {
                $schemaData = AccessTable::getPageAccess($table, $event->data['id']);
                if ($schemaData->getSchema()->isEditable()) {
                    $schemaData->clearData();
                }
            }
        } elseif ($this->tosave) {
            // save the provided data
            foreach ($this->tosave as $validation) {
                if ($validation->getAccessTable()->getSchema()->isEditable()) {
                    $validation->saveData($event->data['newRevision']);

                    // make sure this schema is assigned
                    $assignments->assignPageSchema(
                        $event->data['id'],
                        $validation->getAccessTable()->getSchema()->getTable()
                    );
                }
            }
        }
        return true;
    }
}

// vim:ts=4:sw=4:et:
