<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Creates the SQL query using the QueryBuilder
 *
 * @internal This class is used by the Search class and should probably never be used directly
 */
class SearchSQLBuilder
{
    /** @var QueryBuilder */
    protected $qb;

    /** @var bool Include latest = 1 in select query */
    protected $selectLatest = true;

    /**
     * SearchSQLBuilder constructor.
     */
    public function __construct()
    {
        $this->qb = new QueryBuilder();
    }

    /**
     * Add the schemas to the query
     *
     * @param Schema[] $schemas Schema names to query
     * @param array $joins Conditionals to be used when joining tables
     */
    public function addSchemas($schemas, $joins)
    {
        // basic tables
        $first_table = '';
        $added_schemas = [];
        foreach ($schemas as $schema) {
            $datatable = 'data_' . $schema->getTable();
            $new_pid = false;
            if ($first_table) {
                // follow up tables
                [$lcol, $rcol] = $joins[$schema->getTable()];
                if ($lcol->getLabel() == '%pageid%' and $rcol->getLabel() == '%pageid%') {
                    // Simple (default) case where we join on page IDs
                    $this->qb->addLeftJoin($first_table, $datatable, $datatable, "$first_table.pid = $datatable.pid");
                } else {
                    // Custom join on some other columns
                    $lefttable = 'data_' . $lcol->getTable();
                    $righttable = 'data_' . $rcol->getTable();
                    $on = $lcol->getType()->joinCondition(
                        $this->qb,
                        $lefttable,
                        $lcol->getColName(),
                        $righttable,
                        $rcol->getColName(),
                        $rcol->getType()
                    );
                    $this->qb->addLeftJoin($lefttable, $righttable, $righttable, $on);
                }
            } else {
                // first table
                $this->qb->addTable($datatable);

                // add conditional schema assignment check
                $this->qb->addLeftJoin(
                    $datatable,
                    'schema_assignments',
                    '',
                    "$datatable.pid != ''
                    AND $datatable.pid = schema_assignments.pid
                    AND schema_assignments.tbl = '{$schema->getTable()}'"
                );

                // add conditional page clauses if pid has a value
                $subAnd = $this->qb->filters()->whereSubAnd();
                $subAnd->whereAnd("$datatable.pid = ''");
                $subOr = $subAnd->whereSubOr();
                $subOr->whereAnd("GETACCESSLEVEL($datatable.pid) > 0");
                $subOr->whereAnd("PAGEEXISTS($datatable.pid) = 1");
                // make sure to check assignment for page data only
                $subOr->whereAnd("($datatable.rid != 0 OR (ASSIGNED = 1 OR ASSIGNED IS NULL))");

                $this->qb->addSelectColumn($datatable, 'rid');
                $this->qb->addSelectColumn($datatable, 'pid', 'PID');
                $this->qb->addSelectColumn($datatable, 'rev');
                $this->qb->addSelectColumn('schema_assignments', 'assigned', 'ASSIGNED');
                $this->qb->addGroupByColumn($datatable, 'pid');
                $this->qb->addGroupByColumn($datatable, 'rid');

                $first_table = $datatable;
            }
            $this->qb->filters()->whereAnd($this->addPublishClauses($datatable));
        }
    }

    /**
     * Add the columns to select, handling multis
     *
     * @param Column[] $columns
     */
    public function addColumns($columns)
    {
        $sep = Search::CONCAT_SEPARATOR;
        $n = 0;
        foreach ($columns as $col) {
            $col->getType()->select(
                $this->qb,
                'data_' . $col->getTable(),
                'multi_' . $col->getTable(),
                'C' . $n++,
                true,
                $sep
            );
        }
    }

    /**
     * Add the filters to the query
     *
     * All given filters are added to their own subclause.
     *
     * @param array[] $filters
     */
    public function addFilters($filters)
    {
        if (!$filters) return; // ignore empty filters

        $subClause = $this->qb->filters()->where('AND');

        foreach ($filters as $filter) {
            /** @var Column $col */
            [$col, $value, $comp, $op] = $filter;

            $datatable = "data_{$col->getTable()}";
            $multitable = "multi_{$col->getTable()}";

            /** @var $col Column */
            if ($col->isMulti()) {
                $MN = $this->qb->generateTableAlias('MN');

                $this->qb->addLeftJoin(
                    $datatable,
                    $multitable,
                    $MN,
                    "$datatable.pid = $MN.pid AND $datatable.rid = $MN.rid AND
                     $datatable.rev = $MN.rev AND
                     $MN.colref = {$col->getColref()}"
                );
                $coltbl = $MN;
                $colnam = 'value';
            } else {
                $coltbl = $datatable;
                $colnam = $col->getColName();
            }

            $col->getType()->filter($subClause, $coltbl, $colnam, $comp, $value, $op); // type based filter
        }
    }

    /**
     * Add the sort by clauses to the query
     *
     * We always sort by the single val column which contains a copy of the first value of the multi column
     *
     * @param array[] $sorts
     */
    public function addSorts($sorts)
    {
        foreach ($sorts as $sort) {
            [$col, $asc, $nc] = $sort;
            /** @var $col Column */
            $colname = $col->getColName(false);
            if ($nc) $colname .= ' COLLATE NOCASE';
            $col->getType()->sort($this->qb, 'data_' . $col->getTable(), $colname, $asc ? 'ASC' : 'DESC');
        }
    }

    /**
     * @param string $datatable
     * @return string
     */
    public function addPublishClauses($datatable)
    {
        $latestClause = "IS_PUBLISHER($datatable.pid)";
        if ($this->selectLatest) {
            $latestClause .= " AND $datatable.latest = 1";
        }
        $publishedClause = "IS_PUBLISHER($datatable.pid) !=1 AND $datatable.published = 1";

        return "( ($latestClause) OR ($publishedClause) )";
    }

    /**
     * Access to the underlying QueryBuilder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }

    /**
     * Get the SQL query and parameters
     *
     * Shortcut for $this->getQueryBuilder()->getSQL()
     *
     * @return array ($sql, $params)
     */
    public function getSQL()
    {
        return $this->qb->getSQL();
    }

    /**
     * Allows disabling default 'latest = 1' clause in select statement
     *
     * @param bool $selectLatest
     */
    public function setSelectLatest(bool $selectLatest)
    {
        $this->selectLatest = $selectLatest;
    }
}
