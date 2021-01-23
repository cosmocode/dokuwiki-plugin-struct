<?php

namespace dokuwiki\plugin\struct\meta;

class CSVSerialImporter extends CSVImporter
{

    /** @var bool[]  */
    protected $createPage = [];

    /**
     * Import page schema only when the pid header is present.
     */
    protected function readHeaders()
    {
        parent::readHeaders();
        if (!in_array('pid', $this->header)) throw new StructException('There is no "pid" header in the CSV. Schema not imported.');
    }

    /**
     * Add the revision.
     *
     * @param string[] $values
     */
    protected function saveLine($values)
    {
        // create new page
        $pid = cleanID($values[0]);
        if ($this->createPage[$pid]) {
            $this->createPage($pid, $values);
        }

        parent::saveLine($values);
    }

    /**
     * Create a page with serial syntax, either from a namespace template with _serial suffix
     * or an empty one.
     *
     * @param string $pid
     * @param array  $line
     */
    protected function createPage($pid, $line)
    {
        $text = pageTemplate($pid);
        if (trim($text) === '') {
            $pageParts = explode(':', $pid);
            $pagename = end($pageParts);
            $text = "====== $pagename ======\n";
        }

        // add serial syntax
        $schema = $this->schema->getTable();
        $text .= "
---- struct serial ----
schema: $schema
cols: *
----
";
        saveWikiText($pid, $text, 'Created by struct csv import');
    }

    /**
     * Check if page id realy exists
     *
     * @param Column $col
     * @param mixed  $rawvalue
     * @return bool
     */
    protected function validateValue(Column $col, &$rawvalue)
    {
        global $INPUT;
        if ($col->getLabel() !== 'pid' || !$INPUT->bool('createPage')) {
            return parent::validateValue($col, $rawvalue);
        }

        $pid = cleanID($rawvalue);
        if (!page_exists($pid)) {
            $this->createPage[$pid] = true;
        }
        return true;
    }
}
