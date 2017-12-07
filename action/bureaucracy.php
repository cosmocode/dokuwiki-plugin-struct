<?php
/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\Search;

/**
 * Handles bureaucracy additions
 *
 * This registers to the template action of the bureaucracy plugin and saves all struct data
 * submitted through the bureaucracy form to all newly created pages (if the schema applies).
 *
 * It also registers the struct_schema type for bureaucracy which will add all fields of the
 * schema to the form. The struct_field type is added through standard naming convention - see
 * helper/fiels.php for that.
 */
class action_plugin_struct_bureaucracy extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('PLUGIN_BUREAUCRACY_TEMPLATE_SAVE', 'BEFORE', $this, 'handle_lookup_fields');
        $controller->register_hook('PLUGIN_BUREAUCRACY_TEMPLATE_SAVE', 'AFTER', $this, 'handle_save');
        $controller->register_hook('PLUGIN_BUREAUCRACY_FIELD_UNKNOWN', 'BEFORE', $this, 'handle_schema');
    }

    /**
     * Load a whole schema as fields
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handle_schema(Doku_Event $event, $param) {
        $args = $event->data['args'];
        if($args[0] != 'struct_schema') return false;
        $event->preventDefault();
        $event->stopPropagation();

        /** @var helper_plugin_bureaucracy_field $helper */
        $helper = plugin_load('helper', 'bureaucracy_field');
        $helper->initialize($args);

        $schema = new Schema($helper->opt['label']);
        if(!$schema->getId()) {
            msg('This schema does not exist', -1);
            return false;
        }

        foreach($schema->getColumns(false) as $column) {
            /** @var helper_plugin_struct_field $field */
            $field = plugin_load('helper', 'struct_field');
            // we don't initialize the field but set the appropriate values
            $field->opt = $helper->opt; // copy all the settings to each field
            $field->opt['label'] = $column->getFullQualifiedLabel();
            $field->column = $column;
            $event->data['fields'][] = $field;
        }
        return true;
    }

    /**
     * Replace lookup fields placeholder's values
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handle_lookup_fields(Doku_Event $event, $param) {
        foreach($event->data['fields'] as $field) {
            if(!is_a($field, 'helper_plugin_struct_field')) continue;
            if($field->column->getType()->getClass() != 'Lookup') continue;

            $pid = $field->getParam('value');
            $config = $field->column->getType()->getConfig();

            // find proper value
            // current Search implementation doesn't allow doing it using SQL
            $search = new Search();
            $search->addSchema($config['schema']);
            $search->addColumn($config['field']);
            $result = $search->execute();
            $pids = $search->getPids();
            $len = count($result);

            $value = '';
            for($i = 0; $i < $len; $i++) {
                if ($pids[$i] == $pid) {
                   $value = $result[$i][0]->getDisplayValue();
                   break;
                }
            }

            //replace previous value
            if ($value) {
                $event->data['values'][$field->column->getFullQualifiedLabel()] = $value;
            }
        }
        return true;
    }

    /**
     * Save the struct data
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handle_save(Doku_Event $event, $param) {
        // get all struct values and their associated schemas
        $tosave = array();
        foreach($event->data['fields'] as $field) {
            if(!is_a($field, 'helper_plugin_struct_field')) continue;
            /** @var helper_plugin_struct_field $field */
            $tbl = $field->column->getTable();
            $lbl = $field->column->getLabel();
            if(!isset($tosave[$tbl])) $tosave[$tbl] = array();
            $tosave[$tbl][$lbl] = $field->getParam('value');
        }

        // save all the struct data of assigned schemas
        $id = $event->data['id'];
        $time = filemtime(wikiFN($id));

        $assignments = Assignments::getInstance();
        $assigned = $assignments->getPageAssignments($id);
        foreach($tosave as $table => $data) {
            if(!in_array($table, $assigned)) continue;
            $access = AccessTable::byTableName($table, $id, $time);
            $validator = $access->getValidator($data);
            if($validator->validate()) {
                $validator->saveData($time);

                // make sure this schema is assigned
                $assignments->assignPageSchema(
                    $id,
                    $validator->getAccessTable()->getSchema()->getTable()
                );

                // trigger meta data rendering to set page title
                p_get_metadata($id);
            }
        }

        // expire the cache in order to correctly render the struct header on the first page visit
        p_set_metadata($id, array('cache' => 'expire'));

        return true;
    }

}

// vim:ts=4:sw=4:et:
