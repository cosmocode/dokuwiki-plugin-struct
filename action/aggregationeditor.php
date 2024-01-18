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
use dokuwiki\plugin\struct\meta\AccessTableGlobal;
use dokuwiki\plugin\struct\meta\AggregationEditorTable;
use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\Value;

/**
 * Class action_plugin_struct_lookup
 *
 * Handle global and serial data table editing
 */
class action_plugin_struct_aggregationeditor extends ActionPlugin
{
    /** @var  Column */
    protected $column;

    /** @var string */
    protected $pid = '';

    /** @var int */
    protected $rid = 0;

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'addJsinfo');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
    }

    /**
     * Add user's permissions to JSINFO
     *
     * @param Event $event
     */
    public function addJsinfo(Event $event)
    {
        global $ID;
        global $JSINFO;
        $JSINFO['plugins']['struct']['isPageEditor'] = auth_quickaclcheck($ID) >= AUTH_EDIT;
    }


    /**
     * @param Event $event
     */
    public function handleAjax(Event $event)
    {
        $len = strlen('plugin_struct_aggregationeditor_');
        if (substr($event->data, 0, $len) != 'plugin_struct_aggregationeditor_') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        try {
            if (substr($event->data, $len) == 'new') {
                $this->newRowEditor();
            }

            if (substr($event->data, $len) == 'save') {
                $this->saveRow();
            }

            if (substr($event->data, $len) == 'delete') {
                $this->deleteRow();
            }
        } catch (StructException $e) {
            http_status(500);
            header('Content-Type: text/plain');
            echo $e->getMessage();
        }
    }

    /**
     * Deletes a row
     */
    protected function deleteRow()
    {
        global $INPUT;
        $tablename = $INPUT->str('schema');
        if (!$tablename) {
            throw new StructException('No schema given');
        }

        $this->rid = $INPUT->int('rid');
        $this->validate();

        action_plugin_struct_inline::checkCSRF();

        $access = $this->getAccess($tablename);
        if (!$access->getSchema()->isEditable()) {
            throw new StructException('lookup delete error: no permission for schema');
        }
        $access->clearData();
    }

    /**
     * Save one new row
     */
    protected function saveRow()
    {
        global $INPUT;
        $tablename = $INPUT->str('schema');
        $data = $INPUT->arr('entry');
        $this->pid = $INPUT->str('pid');
        action_plugin_struct_inline::checkCSRF();

        // create a new row based on the original aggregation config
        $access = $this->getAccess($tablename);

        /** @var helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        $helper->saveLookupData($access, $data);

        $config = json_decode($INPUT->str('searchconf'), true, 512, JSON_THROW_ON_ERROR);
        // update row id
        $this->rid = $access->getRid();
        $config = $this->addTypeFilter($config);

        $editorTable = new AggregationEditorTable(
            $this->pid,
            'xhtml',
            new Doku_Renderer_xhtml(),
            new SearchConfig($config)
        );

        echo $editorTable->getFirstRow();
    }

    /**
     * Create the Editor for a new row
     */
    protected function newRowEditor()
    {
        global $INPUT;
        global $lang;

        $searchconf = $INPUT->arr('searchconf');
        $tablename = $searchconf['schemas'][0][0];

        $schema = new Schema($tablename);
        if (!$schema->isEditable()) {
            return;
        } // no permissions, no editor
        // separate check for serial data in JS

        echo '<div class="struct_entry_form">';
        echo '<fieldset>';
        echo '<legend>' . $this->getLang('lookup new entry') . '</legend>';
        /** @var action_plugin_struct_edit $edit */
        $edit = plugin_load('action', 'struct_edit');

        // filter columns based on searchconf cols from syntax
        $columns = $this->resolveColumns($searchconf, $schema);

        foreach ($columns as $column) {
            $label = $column->getLabel();
            $field = new Value($column, '');
            echo $edit->makeField($field, "entry[$label]");
        }
        formSecurityToken(); // csrf protection
        echo '<input type="hidden" name="call" value="plugin_struct_aggregationeditor_save" />';
        echo '<input type="hidden" name="schema" value="' . hsc($tablename) . '" />';

        echo '<button type="submit">' . $lang['btn_save'] . '</button>';

        echo '<div class="err"></div>';
        echo '</fieldset>';
        echo '</div>';
    }

    /**
     * Names of columns in the new entry editor: either all,
     * or the selection defined in config. If config contains '*',
     * just return the full set.
     *
     * @param array $searchconf
     * @param Schema $schema
     * @return array
     */
    protected function resolveColumns($searchconf, $schema)
    {
        // if no valid column config, return all columns
        if (
            empty($searchconf['cols']) ||
            !is_array($searchconf['cols']) ||
            in_array('*', $searchconf['cols'])
        ) {
            return $schema->getColumns(false);
        }

        $columns = [];
        foreach ($searchconf['cols'] as $col) {
            $columns[] = $schema->findColumn($col);
        }
        // filter invalid columns (where findColumn() returned false)
        return array_filter($columns);
    }

    /**
     * Returns data accessor
     *
     * @param string $tablename
     * @return AccessTableGlobal
     */
    protected function getAccess($tablename)
    {
        if ($this->pid) {
            return AccessTable::getSerialAccess($tablename, $this->pid, $this->rid);
        }
        return AccessTable::getGlobalAccess($tablename, $this->rid);
    }

    /**
     * Adds filter to search config to differentiate data types
     *
     * @param array $config
     * @return array
     */
    protected function addTypeFilter($config)
    {
        $config['filter'][] = ['%rowid%', '=', $this->rid, 'AND'];
        if ($this->pid) {
            $config['filter'][] = ['%pageid%', '=', $this->pid, 'AND'];
        }
        return $config;
    }

    /**
     * Throws an exception if data is invalid
     */
    protected function validate()
    {
        if (!$this->rid) {
            throw new StructException('No row id given');
        }
    }
}
