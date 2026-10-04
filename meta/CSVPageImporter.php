<?php

namespace dokuwiki\plugin\struct\meta;

class CSVPageImporter extends CSVImporter
{
    protected $importedPids = [];

    /** @var bool[] */
    protected $createPage = [];

    /**
     * Import page schema only when the pid header is present.
     */
    protected function readHeaders()
    {
        parent::readHeaders();
        if (!in_array('pid', $this->header))
            throw new StructException('There is no "pid" header in the CSV. Schema not imported.');
    }

    /**
     * Add the revision.
     *
     * @param string[] $values
     */
    protected function saveLine($values)
    {
        //create new page revision
        $pid = cleanID($values[0]);
        if (isset($this->createPage[$pid])) {
            $this->createPage($pid, $values);
        }
        // make sure this schema is assigned
        /** @noinspection PhpUndefinedVariableInspection */
        Assignments::getInstance()->assignPageSchema(
            $pid,
            $this->schema->getTable()
        );
        parent::saveLine($values);
    }

    /**
     * Create a page from a namespace template and replace column-label-placeholders
     *
     * This is intended to use the same placeholders as bureaucracy in their most basic version
     * (i.e. without default values, formatting, etc. )
     *
     * @param string $pid
     * @param array $line
     */
    protected function createPage($pid, $line)
    {
        $text = pageTemplate($pid);
        if (trim($text) === '') {
            $pageParts = explode(':', $pid);
            $pagename = end($pageParts);
            $text = "====== $pagename ======\n";
        }
        $keys = array_reduce(
            $this->columns,
            function ($keys, Column $col) {
                if (!in_array($col->getLabel(), $keys, true)) {
                    return $keys;
                }
                $index = array_search($col->getLabel(), $keys, true);
                $keys[$index] = $col->getFullQualifiedLabel();
                return $keys;
            },
            $this->header
        );

        $keysAt = array_map(static fn($key) => "@@$key@@", $keys);
        $keysHash = array_map(static fn($key) => "##$key##", $keys);
        $flatValues = array_map(
            function ($value) {
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value;
            },
            $line
        );
        $text = $this->evaluateIfNotEmptyTags($text, $keys, $flatValues);
        $text = str_replace($keysAt, $flatValues, $text);
        /** @noinspection CascadeStringReplacementInspection */
        $text = str_replace($keysHash, $flatValues, $text);
        saveWikiText($pid, $text, 'Created by struct csv import');
    }

    /**
     * Replace conditional <ifnotempty fieldname></ifnotempty> tags
     *
     * @param string $text The template
     * @param string[] $keys The array of qualified headers
     * @param string[] $values The flat array of corresponding values
     *
     * @return string The template with the tags replaced
     */
    protected function evaluateIfNotEmptyTags($text, $keys, $values)
    {
        return preg_replace_callback(
            '/<ifnotempty (.+?)>([^<]*?)<\/ifnotempty>/',
            function ($matches) use ($keys, $values) {
                [, $blockKey, $textIfNotEmpty] = $matches;
                $index = array_search($blockKey, $keys, true);
                if ($index === false) {
                    msg('Import error: Key "' . hsc($blockKey) . '" not found!', -1);
                    return '';
                }
                if (trim($values[$index]) === '') {
                    return '';
                }
                return $textIfNotEmpty;
            },
            $text
        );
    }

    /**
     * Check if page id realy exists
     *
     * @param Column $col
     * @param mixed $rawvalue
     * @return bool
     */
    protected function validateValue(Column $col, &$rawvalue)
    {
        //check if page id exists and schema is bound to the page
        if ($col->getLabel() == 'pid') {
            $pid = cleanID($rawvalue);
            if (isset($this->importedPids[$pid])) {
                $this->errors[] = 'Page "' . $pid . '" already imported. Skipping the row.';
                return false;
            }
            if (page_exists($pid)) {
                $this->importedPids[$pid] = true;
                return true;
            }
            global $INPUT;
            if ($INPUT->bool('createPage')) {
                $this->createPage[$pid] = true;
                return true;
            }
            $this->errors[] = 'Page "' . $pid . '" does not exists. Skipping the row.';
            return false;
        }

        return parent::validateValue($col, $rawvalue);
    }
}
