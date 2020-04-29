<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class CSVExporter
 *
 * exports raw schema data to CSV. For lookup schemas this data can be reimported again through
 * CSVImporter
 *
 * Note this is different from syntax/csv.php
 *
 * @package dokuwiki\plugin\struct\meta
 */
class CSVExporter
{
    /**
     * CSVImporter constructor.
     *
     * @throws StructException
     * @param string $table
     */
    public function __construct($table)
    {

        $search = new Search();
        $search->addSchema($table);
        $search->addColumn('*');
        $result = $search->execute();

        $pids = $search->getPids();

        echo $this->header($search->getColumns());
        foreach ($result as $i => $row) {
            $pid = $pids[$i];
            echo $this->row($row, $pid);
        }
    }

    /**
     * Create the header
     *
     * @param Column[] $columns
     * @return string
     */
    protected function header($columns)
    {
        $row = '';
        $row .= $this->escape('pid');
        $row .= ',';

        foreach ($columns as $i => $col) {
            $row .= $this->escape($col->getLabel());
            $row .= ',';
        }
        return rtrim($row, ',') . "\r\n";
    }

    /**
     * Create one row of data
     *
     * @param Value[] $values
     * @param string $pid pid of this row
     * @return string
     */
    protected function row($values, $pid)
    {
        $row = '';
        $row .= $this->escape($pid);
        $row .= ',';

        foreach ($values as $value) {
            /** @var Value $value */
            $val = $value->getRawValue();
            if (is_array($val)) $val = join(',', $val);

            $row .= $this->escape($val);
            $row .= ',';
        }

        return rtrim($row, ',') . "\r\n";
    }

    /**
     * Escapes and wraps the given string
     *
     * Uses doubled quotes for escaping which seems to be the standard escaping method for CSV
     *
     * @param string $str
     * @return string
     */
    protected function escape($str)
    {
        return '"' . str_replace('"', '""', $str) . '"';
    }
}
