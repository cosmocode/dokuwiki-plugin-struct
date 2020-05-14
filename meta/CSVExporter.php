<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class CSVExporter
 *
 * exports raw schema data to CSV.
 *
 * Note this is different from syntax/csv.php
 *
 * @package dokuwiki\plugin\struct\meta
 */
class CSVExporter
{
    const DATATYPE_PAGE = 'page';
    const DATATYPE_GLOBAL = 'global';
    const DATATYPE_SERIAL = 'serial';

    protected $type = '';

    /**
     * CSVExporter constructor.
     *
     * @param string $table
     * @param string $type
     */
    public function __construct($table, $type)
    {
        // TODO make it nicer
        $this->type = $type;

        $search = new Search();
        $search->addSchema($table);
        $search->addColumn('*');
        $result = $search->execute();

        if ($this->type !== self::DATATYPE_GLOBAL) {
            $pids = $search->getPids();
        }

        echo $this->header($search->getColumns());
        foreach ($result as $i => $row) {
            if ($this->type !== self::DATATYPE_GLOBAL) {
                $pid = $pids[$i];
            } else {
                $pid = '';
            }
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

        if ($this->type !== self::DATATYPE_GLOBAL) {
            $row .= $this->escape('pid');
            $row .= ',';
        }

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
        if ($this->type !== self::DATATYPE_GLOBAL) {
            $row .= $this->escape($pid);
            $row .= ',';
        }

        foreach ($values as $value) {
            /** @var Value $value */
            $val = $value->getRawValue();
            if (is_array($val)) $val = join(',', $val);

            // FIXME check escaping of composite ids (JSON with """")
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
