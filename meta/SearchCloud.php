<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class SearchCloud
 *
 * The same as @see SearchConfig, but executed a search that is not pid-focused
 *
 * @package dokuwiki\plugin\struct\meta
 */
class SearchCloud extends SearchConfig
{
    protected $limit = '';

    /**
     * Transform the set search parameters into a statement
     *
     * @return array ($sql, $opts) The SQL and parameters to execute
     */
    public function getSQL()
    {
        if (!$this->columns) throw new StructException('nocolname');

        $QB = new QueryBuilder();
        reset($this->schemas);
        $schema = current($this->schemas);
        $datatable = 'data_' . $schema->getTable();

        $QB->addTable($datatable);

        // add conditional page clauses if pid has a value
        $subAnd = $QB->filters()->whereSubAnd();
        $subAnd->whereAnd("$datatable.pid = ''");

        $subOr = $subAnd->whereSubOr();
        $subOr->whereAnd("GETACCESSLEVEL($datatable.pid) > 0");
        $subOr->whereAnd("PAGEEXISTS($datatable.pid) = 1");
        $subOr->whereSubOr()
            ->whereAnd('ASSIGNED == 1')
            ->whereSubOr()
                ->whereAnd("$datatable.rid > 0")
                ->whereAnd("ASSIGNED IS NULL");

        // add conditional schema assignment check
        $QB->addLeftJoin(
            $datatable,
            'schema_assignments',
            '',
            "$datatable.pid != ''
                    AND $datatable.pid = schema_assignments.pid
                    AND schema_assignments.tbl = '{$schema->getTable()}'"
        );

        $QB->filters()->whereAnd("$datatable.latest = 1");
        $QB->filters()->where('AND', 'tag IS NOT \'\'');

        $col = $this->columns[0];
        $col->getType()->select($QB, 'data_' . $datatable, 'multi_' . $col->getTable(), 'tag', true);

        $QB->addSelectStatement('COUNT(tag)', 'count');
        $QB->addSelectColumn('schema_assignments', 'assigned', 'ASSIGNED');
        if ($col->isMulti()) {
            // This GROUP BY was added with the SELECT statement for a
            // single-valued column, just need to make sure it's added
            // in the case of multi-valued as well.
            $QB->addGroupByStatement('tag');
        }
        $QB->addOrderBy('count DESC');

        [$sql, $opts] = $QB->getSQL();
        return [$sql . $this->limit, $opts];
    }

    /**
     * We do not have pagination in clouds, so we can work with a limit within SQL
     *
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = " LIMIT $limit";
    }

    /**
     * Execute this search and return the result
     *
     * The result is a two dimensional array of Value()s.
     *
     * @return Value[][]
     */
    public function execute()
    {
        [$sql, $opts] = $this->getSQL();

        /** @var \PDOStatement $res */
        $res = $this->sqlite->query($sql, $opts);
        if ($res === false) throw new StructException("SQL execution failed for\n\n$sql");

        $result = [];
        $rows = $res->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (!empty($this->config['min']) && $this->config['min'] > $row['count']) {
                break;
            }

            $row['tag'] = new Value($this->columns[0], $row['tag']);
            $result[] = $row;
        }

        $res->closeCursor();
        $this->count = count($result);
        return $result;
    }
}
