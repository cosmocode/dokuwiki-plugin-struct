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
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string|null $oldalias A previous alias used for this table (only used by Page)
     * @param string $colname The column name on the above table
     * @param string &$op the logical operator this filter should use
     * @return string The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias, $oldalias, $colname, &$op)
    {
        return "$table.lastsummary";
    }

    /**
     * This function provides arguments for an additional JOIN operation needed
     * to perform a comparison (e.g., for a JOIN or FILTER), or null if no
     * additional JOIN is needed.
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string $colname The column name on the above table
     * @return null|array [$leftalias, $righttable, $rightalias, $onclause]
     */
    protected function getAdditionalJoinForComparison(QueryBuilderWhere &$add, $tablealias, $colname)
    {
        $QB = $add->getQB();
        $rightalias = $QB->generateTableAlias();
        return [$tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid"];
    }
}
