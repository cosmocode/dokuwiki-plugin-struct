<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\SearchConfigParameters;
use dokuwiki\Utf8\PhpString;

class Tag extends AbstractMultiBaseType
{
    protected $config = [
        'page' => '',
        'autocomplete' => [
            'mininput' => 2,
            'maxresult' => 5
        ]
    ];

    /**
     * @param int|string $value
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        global $INFO;

        $context = $this->getContext();
        $filter = SearchConfigParameters::$PARAM_FILTER .
            '[' . $context->getTable() . '.' . $context->getLabel() . '*~]=' . $value;

        $page = trim($this->config['page']);
        if (!$page) $page = $INFO['id'];

        $R->internallink($page . '?' . $filter, $value);
        return true;
    }

    /**
     * Autocomplete from existing tags
     *
     * @return array
     */
    public function handleAjax()
    {
        global $INPUT;

        // check minimum length
        $lookup = trim($INPUT->str('search'));
        if (PhpString::strlen($lookup) < $this->config['autocomplete']['mininput']) return [];

        // results wanted?
        $max = $this->config['autocomplete']['maxresult'];
        if ($max <= 0) return [];

        $context = $this->getContext();
        $sql = $this->buildSQLFromContext($context);
        $opt = ["%$lookup%"];

        /** @var \helper_plugin_struct_db $hlp */
        $hlp = plugin_load('helper', 'struct_db');
        $sqlite = $hlp->getDB();
        $rows = $sqlite->queryAll($sql, $opt);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'label' => $row['value'],
                'value' => $row['value']
            ];
        }

        return $result;
    }

    /**
     * Create the sql to query the database for tags to do autocompletion
     *
     * This method both handles multi columns and page schemas that need access checking
     *
     * @param Column $context
     *
     * @return string The sql with a single "?" placeholde for the search value
     */
    protected function buildSQLFromContext(Column $context)
    {
        $sql = '';
        if ($context->isMulti()) {
            /** @noinspection SqlResolve */
            $sql .= "SELECT DISTINCT value
                      FROM multi_{$context->getTable()} AS M, data_{$context->getTable()} AS D
                     WHERE M.pid = D.pid
                       AND M.rev = D.rev
                       AND M.colref = {$context->getColref()}\n";
        } else {
            /** @noinspection SqlResolve */
            $sql .= "SELECT DISTINCT col{$context->getColref()} AS value
                      FROM data_{$context->getTable()} AS D
                     WHERE 1 = 1\n";
        }

        $sql .= "AND ( D.pid = '' OR (";
        $sql .= "PAGEEXISTS(D.pid) = 1\n";
        $sql .= "AND GETACCESSLEVEL(D.pid) > 0\n";
        $sql .= ")) ";

        $sql .= "AND D.latest = 1\n";
        $sql .= "AND value LIKE ?\n";
        $sql .= 'ORDER BY value';

        return $sql;
    }

    /**
     * Normalize tags before comparing
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $comp
     * @param string|string[] $value
     * @param string $op
     */
    public function filter(QueryBuilderWhere $add, $tablealias, $colname, $comp, $value, $op)
    {
        /** @var QueryBuilderWhere $add Where additionional queries are added to */
        if (is_array($value)) {
            $add = $add->where($op); // sub where group
            $op = 'OR';
        }
        foreach ((array)$value as $item) {
            $pl = $add->getQB()->addValue($item);
            $add->where($op, "LOWER(REPLACE($tablealias.$colname, ' ', '')) $comp LOWER(REPLACE($pl, ' ', ''))");
        }
    }
}
