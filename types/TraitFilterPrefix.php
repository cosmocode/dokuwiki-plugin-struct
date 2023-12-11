<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\QueryBuilderWhere;

/**
 * Class TraitFilterPrefix
 *
 * This implements a filter function for Types that use pre- or post fixes. It makes sure
 * given values are checked against the pre/postfixed values from the database
 *
 * @package dokuwiki\plugin\struct\types
 */
trait TraitFilterPrefix
{
    /**
     * Comparisons are done against the full string (including prefix/postfix)
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string|null $oldalias A previous alias used for this table (only used by Page)
     * @param string $colname The column name on the above table
     * @param string &$op the logical operator this filter should use
     * @return string|array The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias, $oldalias, $colname, &$op)
    {
        $column = parent::getSqlCompareValue($add, $tablealias, $oldalias, $colname, $op);

        $add = $add->where($op); // open a subgroup
        $add->where('AND', "$column != ''"); // make sure the field isn't empty
        $op = 'AND';

        $QB = $add->getQB();
        if ($this->config['prefix']) {
            $pl = $QB->addValue($this->config['prefix']);
            $column = "$pl || $column";
        }
        if ($this->config['postfix']) {
            $pl = $QB->addValue($this->config['postfix']);
            $column = "$column || $pl";
        }
        return $column;
    }
}
