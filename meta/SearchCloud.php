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
     * We do not have pagination in clouds, so we can work with a limit within SQL
     *
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = " LIMIT $limit";
    }

    /**
     * @inheritdoc
     */
    protected function runSQLBuilder()
    {
        $sqlBuilder = new SearchSQLBuilder();
        $sqlBuilder->setSelectLatest($this->selectLatest);
        $sqlBuilder->addSchemas($this->schemas, false);
        $this->addTagSelector($sqlBuilder);
        $sqlBuilder->getQueryBuilder()->addGroupByStatement('tag');
        $sqlBuilder->getQueryBuilder()->addOrderBy('count DESC');
        $sqlBuilder->addFilters($this->filter);
        return $sqlBuilder;
    }

    /**
     * Add the tag selector to the SQLBuilder
     */
    protected function addTagSelector(SearchSQLBuilder $builder)
    {
        $QB = $builder->getQueryBuilder();

        $col = $this->columns[0];
        $datatable = "data_{$col->getTable()}";

        if ($col->isMulti()) {
            $multitable = "multi_{$col->getTable()}";
            $MN = $QB->generateTableAlias('M');

            $QB->addLeftJoin(
                $datatable,
                $multitable,
                $MN,
                "$datatable.pid = $MN.pid AND
                     $datatable.rid = $MN.rid AND
                     $datatable.rev = $MN.rev AND
                     $MN.colref = {$col->getColref()}"
            );

            $col->getType()->select($QB, $MN, 'value', 'tag');
            $colname = $MN . '.value';
        } else {
            $col->getType()->select($QB, $datatable, $col->getColName(), 'tag');
            $colname = $datatable . '.' . $col->getColName();
        }
        $QB->addSelectStatement("COUNT($colname)", 'count');
    }


    /**
     * Execute this search and return the result
     *
     * Because the cloud uses a different search, we omit calling
     * getResult() und run() methods of the parent class, and return the result array directly.
     *
     * @return Value[][]
     */
    public function getRows()
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
        return $result;
    }
}
