<?php

/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
use dokuwiki\plugin\struct\meta\AccessDataValidator;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\AccessTableGlobal;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\StructException;

if (!defined('DOKU_INC')) die();

/**
 * The public interface for the struct plugin
 *
 * 3rd party developers should always interact with struct data through this
 * helper plugin only. If additionional interface functionality is needed,
 * it should be added here.
 *
 * All functions will throw StructExceptions when something goes wrong.
 *
 * Remember to check permissions yourself!
 */
class helper_plugin_struct extends DokuWiki_Plugin
{

    /**
     * Get the structured data of a given page
     *
     * @param string $page The page to get data for
     * @param string|null $schema The schema to use null for all
     * @param int $time A timestamp if you want historic data
     * @return array ('schema' => ( 'fieldlabel' => 'value', ...))
     * @throws StructException
     */
    public function getData($page, $schema = null, $time = 0)
    {
        $page = cleanID($page);
        if (!$time) {
            $time = time();
        }

        if (is_null($schema)) {
            $assignments = Assignments::getInstance();
            $schemas = $assignments->getPageAssignments($page, false);
        } else {
            $schemas = array($schema);
        }

        $result = array();
        foreach ($schemas as $schema) {
            $schemaData = AccessTable::getPageAccess($schema, $page, $time);
            $result[$schema] = $schemaData->getDataArray();
        }

        return $result;
    }

    /**
     * Saves data for a given page (creates a new revision)
     *
     * If this call succeeds you can assume your data has either been saved or it was
     * not necessary to save it because the data already existed in the wanted form or
     * the given schemas are no longer assigned to that page.
     *
     * Important: You have to check write permissions for the given page before calling
     * this function yourself!
     *
     * this duplicates a bit of code from entry.php - we could also fake post data and let
     * entry handle it, but that would be rather unclean and might be problematic when multiple
     * calls are done within the same request.
     *
     * @todo should this try to lock the page?
     *
     *
     * @param string $page
     * @param array $data ('schema' => ( 'fieldlabel' => 'value', ...))
     * @param string $summary
     * @param string $summary
     * @throws StructException
     */
    public function saveData($page, $data, $summary = '', $minor = false)
    {
        $page = cleanID($page);
        $summary = trim($summary);
        if (!$summary) $summary = $this->getLang('summary');

        if (!page_exists($page)) throw new StructException("Page does not exist. You can not attach struct data");

        // validate and see if anything changes
        $valid = AccessDataValidator::validateDataForPage($data, $page, $errors);
        if ($valid === false) {
            throw new StructException("Validation failed:\n%s", join("\n", $errors));
        }
        if (!$valid) return; // empty array when no changes were detected

        $newrevision = self::createPageRevision($page, $summary, $minor);

        // save the provided data
        $assignments = Assignments::getInstance();
        foreach ($valid as $v) {
            $v->saveData($newrevision);
            // make sure this schema is assigned
            $assignments->assignPageSchema($page, $v->getAccessTable()->getSchema()->getTable());
        }
    }

    /**
     * Save lookup data row
     *
     * @param AccessTable        $access the table into which to save the data
     * @param array             $data   data to be saved in the form of [columnName => 'data']
     */
    public function saveLookupData(AccessTable $access, $data)
    {
        if (!$access->getSchema()->isEditable()) {
            throw new StructException('lookup save error: no permission for schema');
        }
        $validator = $access->getValidator($data);
        if (!$validator->validate()) {
            throw new StructException("Validation failed:\n%s", implode("\n", $validator->getErrors()));
        }
        if (!$validator->saveData()) {
            throw new StructException('No data saved');
        }
    }

    /**
     * Creates a new page revision with the same page content as before
     *
     * @param string $page
     * @param string $summary
     * @param bool $minor
     * @return int the new revision
     */
    public static function createPageRevision($page, $summary = '', $minor = false)
    {
        $summary = trim($summary);
        // force a new page revision @see action_plugin_struct_entry::handle_pagesave_before()
        $GLOBALS['struct_plugin_force_page_save'] = true;
        saveWikiText($page, rawWiki($page), $summary, $minor);
        unset($GLOBALS['struct_plugin_force_page_save']);
        $file = wikiFN($page);
        clearstatcache(false, $file);
        return filemtime($file);
    }

    /**
     * Get info about existing schemas
     *
     * @param string|null $schema the schema to query, null for all
     * @return Schema[]
     * @throws StructException
     */
    public function getSchema($schema = null)
    {
        if (is_null($schema)) {
            $schemas = Schema::getAll();
        } else {
            $schemas = array($schema);
        }

        $result = array();
        foreach ($schemas as $table) {
            $result[$table] = new Schema($table);
        }
        return $result;
    }

    /**
     * Returns all pages known to the struct plugin
     *
     * That means all pages that have or had once struct data saved
     *
     * @param string|null $schema limit the result to a given schema
     * @return array (page => (schema => true), ...)
     * @throws StructException
     */
    public function getPages($schema = null)
    {
        $assignments = Assignments::getInstance();
        return $assignments->getPages($schema);
    }

    public static function decodeJson($value)
    {
        if (!empty($value) && $value[0] !== '[') throw new StructException('Lookup expects JSON');
        return json_decode($value);
    }
}
