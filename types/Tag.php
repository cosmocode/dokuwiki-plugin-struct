<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\SearchConfigParameters;

class Tag extends AbstractMultiBaseType {

    protected $config = array(
        'page' => '',
        'autocomplete' => array(
            'mininput' => 2,
            'maxresult' => 5,
        ),
    );

    /**
     * @param int|string $value
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderValue($value, \Doku_Renderer $R, $mode) {
        $context = $this->getContext();
        $filter = SearchConfigParameters::$PARAM_FILTER . '[' . $context->getTable() . '.' . $context->getLabel() . '*~]=' . $value;

        $page = trim($this->config['page']);
        if(!$page) $page = cleanID($context->getLabel());

        $R->internallink($page . '?' . $filter, $value);
        return true;
    }

    /**
     * Autocomplete from existing tags
     *
     * @return array
     */
    public function handleAjax() {
        global $INPUT;

        // check minimum length
        $lookup = trim($INPUT->str('search'));
        if(utf8_strlen($lookup) < $this->config['autocomplete']['mininput']) return array();

        // results wanted?
        $max = $this->config['autocomplete']['maxresult'];
        if($max <= 0) return array();

        $context = $this->getContext();

        if($context->isMulti()) {
            /** @noinspection SqlResolve */
            $sql = "SELECT DISTINCT value
                      FROM multi_{$context->getTable()} AS M, data_{$context->getTable()} AS D
                     WHERE M.pid = D.pid
                       AND M.rev = D.rev
                       AND D.latest = 1
                       AND PAGEEXISTS(D.pid) = 1
                       AND GETACCESSLEVEL(D.pid) > 0
                       AND M.colref = ?
                       AND value LIKE ?
                  ORDER BY value";
            $opt = array($context->getColref(), "%$lookup%");
        } else {
            /** @noinspection SqlResolve */
            $sql = "SELECT DISTINCT col{$context->getColref()} AS value
                      FROM data_{$context->getTable()} AS D
                     WHERE D.latest = 1
                       AND PAGEEXISTS(D.pid) = 1
                       AND GETACCESSLEVEL(D.pid) > 0
                       AND value LIKE ?
                  ORDER BY value";
            $opt = array("%$lookup%");
        }

        /** @var \helper_plugin_struct_db $hlp */
        $hlp = plugin_load('helper', 'struct_db');
        $sqlite = $hlp->getDB();
        $res = $sqlite->query($sql, $opt);
        $rows = $sqlite->res2arr($res);
        $sqlite->res_close($res);

        $result = array();
        foreach($rows as $row) {
                $result[] = array(
                    'label' => $row['value'],
                    'value' => $row['value'],
                );
        }

        return $result;
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
    public function filter(QueryBuilder $QB, $tablealias, $colname, $comp, $value, $op) {
        /** @var QueryBuilderWhere $add Where additionional queries are added to*/
        if(is_array($value)) {
            $add = $QB->filters()->where($op); // sub where group
            $op = 'OR';
        } else {
            $add = $QB->filters(); // main where clause
        }
        foreach((array) $value as $item) {
            $pl = $QB->addValue($item);
            $add->where($op, "LOWER(REPLACE($tablealias.$colname, ' ', '')) $comp LOWER(REPLACE($pl, ' ', ''))");
        }
    }

}
