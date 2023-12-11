<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\PageColumn;
use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\RevisionColumn;
use dokuwiki\plugin\struct\meta\RowColumn;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\Search;
use dokuwiki\plugin\struct\meta\SummaryColumn;
use dokuwiki\plugin\struct\meta\UserColumn;
use dokuwiki\plugin\struct\meta\Value;

class Lookup extends Dropdown
{
    protected $config = ['schema' => '', 'field' => ''];

    /** @var  Column caches the referenced column */
    protected $column;

    /**
     * Dropdown constructor.
     *
     * @param array|null $config
     * @param string $label
     * @param bool $ismulti
     * @param int $tid
     */
    public function __construct($config = null, $label = '', $ismulti = false, $tid = 0)
    {
        parent::__construct($config, $label, $ismulti, $tid);
        $this->config['schema'] = Schema::cleanTableName($this->config['schema']);
    }

    /**
     * Get the configured loojup column
     *
     * @return Column|false
     */
    protected function getLookupColumn()
    {
        if ($this->column instanceof Column) return $this->column;
        $this->column = $this->getColumn($this->config['schema'], $this->config['field']);
        return $this->column;
    }

    /**
     * Gets the given column, applies language place holder
     *
     * @param string $table
     * @param string $infield
     * @return Column|false
     */
    protected function getColumn($table, $infield)
    {
        global $conf;

        $table = new Schema($table);
        if (!$table->getId()) {
            // schema does not exist
            msg(sprintf('Schema %s does not exist', $table), -1);
            return false;
        }

        // apply language replacement
        $field = str_replace('$LANG', $conf['lang'], $infield);
        $column = $table->findColumn($field);
        if (!$column) {
            $field = str_replace('$LANG', 'en', $infield); // fallback to en
            $column = $table->findColumn($field);
        }
        if (!$column) {
            if ($infield == '%pageid%') {
                $column = new PageColumn(0, new Page(), $table);
            }
            if ($infield == '%title%') {
                $column = new PageColumn(0, new Page(['usetitles' => true]), $table);
            }
            if ($infield == '%lastupdate%') {
                $column = new RevisionColumn(0, new DateTime(), $table);
            }
            if ($infield == '%lasteditor%') {
                $column = new UserColumn(0, new User(), $table);
            }
            if ($infield == '%lastsummary%') {
                return new SummaryColumn(0, new AutoSummary(), $table);
            }
            if ($infield == '%rowid%') {
                $column = new RowColumn(0, new Decimal(), $table);
            }
        }
        if (!$column) {
            // field does not exist
            msg(sprintf('Field %s.%s does not exist', $table, $infield), -1);
            return false;
        }

        if ($column->isMulti()) {
            // field is multi
            msg(sprintf('Field %s.%s is a multi field - not allowed for lookup', $table, $field), -1);
            return false;
        }

        return $column;
    }

    /**
     * Creates the options array
     *
     * @return array
     */
    protected function getOptions()
    {
        $schema = $this->config['schema'];
        $column = $this->getLookupColumn();
        if (!$column) return [];
        $field = $column->getLabel();

        $search = new Search();
        $search->addSchema($schema);
        $search->addColumn($field);
        $search->addSort($field);

        $result = $search->execute();
        $pids = $search->getPids();
        $rids = $search->getRids();
        $len = count($result);

        $options = ['' => ''];
        for ($i = 0; $i < $len; $i++) {
            $val = json_encode([$pids[$i], (int)$rids[$i]]);
            $options[$val] = $result[$i][0]->getDisplayValue();
        }
        return $options;
    }


    /**
     * Render using linked field
     *
     * @param int|string $value
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        [, $value] = \helper_plugin_struct::decodeJson($value);
        $column = $this->getLookupColumn();
        if (!$column) return false;
        return $column->getType()->renderValue($value, $R, $mode);
    }

    /**
     * Render using linked field
     *
     * @param \int[]|\string[] $values
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderMultiValue($values, \Doku_Renderer $R, $mode)
    {
        $values = array_map(
            function ($val) {
                [, $val] = \helper_plugin_struct::decodeJson($val);
                return $val;
            },
            $values
        );
        $column = $this->getLookupColumn();
        if (!$column) return false;
        return $column->getType()->renderMultiValue($values, $R, $mode);
    }

    /**
     * @param string $value
     * @return string
     */
    public function rawValue($value)
    {
        [$value] = \helper_plugin_struct::decodeJson($value);
        return $value;
    }

    /**
     * @param string $value
     * @return string
     */
    public function displayValue($value)
    {
        [, $value] = \helper_plugin_struct::decodeJson($value);
        $column = $this->getLookupColumn();
        if ($column) {
            return $column->getType()->displayValue($value);
        } else {
            return '';
        }
    }

    /**
     * This is the value to be used as argument to a filter for another column.
     *
     * In a sense this is the counterpart to the @param string $value
     *
     * @return string
     * @see filter() function
     *
     */
    public function compareValue($value)
    {
        [, $value] = \helper_plugin_struct::decodeJson($value);
        $column = $this->getLookupColumn();
        if ($column) {
            return $column->getType()->rawValue($value);
        } else {
            return '';
        }
    }


    /**
     * Merge with lookup table
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     */
    public function selectCol(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        $schema = 'data_' . $this->config['schema'];
        $column = $this->getLookupColumn();
        if (!$column) {
            parent::selectCol($QB, $tablealias, $colname, $alias);
            return;
        }

        $field = $column->getColName();
        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin(
            $tablealias,
            $schema,
            $rightalias,
            "$tablealias.$colname = STRUCT_JSON($rightalias.pid, CAST($rightalias.rid AS DECIMAL)) " .
            "AND $rightalias.latest = 1"
        );
        $column->getType()->selectCol($QB, $rightalias, $field, $alias);
        $sql = $QB->getSelectStatement($alias);
        $QB->addSelectStatement("STRUCT_JSON($tablealias.$colname, $sql)", $alias);
    }

    /**
     * Compare against lookup table
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
        $column = $this->getLookupColumn();
        if (!$column) {
            return parent::getSqlCompareValue($add, $tablealias, $oldalias, $colname, $op);
        }
        $field = $column->getColName();
        return $column->getType()->getSqlCompareValue($add, $tablealias, $oldalias, $field, $op);
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
        if (!$this->getLookupColumn()) return null;
        $schema = 'data_' . $this->config['schema'];
        $QB = $add->getQB();
        $rightalias = $QB->generateTableAlias();
        return [
            $tablealias,
            $schema,
            $rightalias,
            "$tablealias.$colname = STRUCT_JSON($rightalias.pid, CAST($rightalias.rid AS DECIMAL)) AND " .
            "$rightalias.latest = 1"
        ];
    }

    /**
     * Handle the value that a column is being compared against.
     *
     * @param string $value The value a column is being compared to
     * @return string A SQL expression processing the value in some way.
     */
    protected function wrapValue($value)
    {
        $schema = 'data_' . $this->config['schema'];
        $column = $this->getLookupColumn();
        if (!$column) {
            return parent::wrapValue($value);
        }
        return $column->getType()->wrapValue($value);
    }

    /**
     * Sort by lookup table
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        $schema = 'data_' . $this->config['schema'];
        $column = $this->getLookupColumn();
        if (!$column) {
            parent::sort($QB, $tablealias, $colname, $order);
            return;
        }
        $field = $column->getColName();

        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin(
            $tablealias,
            $schema,
            $rightalias,
            "$tablealias.$colname = STRUCT_JSON($rightalias.pid, CAST($rightalias.rid AS DECIMAL)) AND " .
            "$rightalias.latest = 1"
        );
        $column->getType()->sort($QB, $rightalias, $field, $order);
    }
}
