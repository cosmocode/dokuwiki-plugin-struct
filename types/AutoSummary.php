<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;

//We prefixing this type with "Abstract" to hide it in Schema Editor
class AutoSummary extends AbstractBaseType
{
    /**
     * When handling `%lastsummary%` get the data from the `titles` table instead the `data_` table.
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     */
    public function selectCol(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
        $QB->addSelectStatement("$rightalias.lastsummary", $alias);
    }

    /**
     * When sorting `%lastsummary%`, then sort the data from the `titles` table instead the `data_` table.
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
        $QB->addOrderBy("$rightalias.lastsummary $order");
    }

    /**
     * When using `%lastsummary%`, we need to compare against the `title` table.
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause which will contain the conditional expression this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string $colname The column name on the above table
     * @param string &$op the logical operator this filter shoudl use
     * @return string The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias,
                                          $colname, &$op) {
        $QB = $add->getQB();
        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");

        return "$rightalias.lastsummary";
    }    
}
