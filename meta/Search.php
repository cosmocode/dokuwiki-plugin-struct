<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\Debug\DebugHelper;
use dokuwiki\Parsing\Lexer\Lexer;
use dokuwiki\plugin\struct\types\AutoSummary;
use dokuwiki\plugin\struct\types\DateTime;
use dokuwiki\plugin\struct\types\Decimal;
use dokuwiki\plugin\struct\types\Page;
use dokuwiki\plugin\struct\types\User;

class Search
{
    /**
     * This separator will be used to concat multi values to flatten them in the result set
     */
    public const CONCAT_SEPARATOR = "\n!_-_-_-_-_!\n";

    /**
     * The list of known and allowed comparators
     * (order matters)
     */
    public static $COMPARATORS = ['<=', '>=', '=*', '=', '<', '>', '!=', '!~', '~', ' IN '];

    /** @var \helper_plugin_struct_db */
    protected $dbHelper;

    /** @var  \helper_plugin_sqlite */
    protected $sqlite;

    /** @var Schema[] list of schemas to query */
    protected $schemas = [];

    /** @var Column[] list of columns to select */
    protected $columns = [];

    /** @var array the sorting of the result */
    protected $sortby = [];

    /** @var array the filters */
    protected $filter = [];

    /** @var array the filters */
    protected $dynamicFilter = [];

    /** @var array list of aliases tables can be referenced by */
    protected $aliases = [];

    /** @var  int begin results from here */
    protected $range_begin = 0;

    /** @var  int end results here */
    protected $range_end = 0;

    /**
     * @var SearchResult
     */
    protected $result;

    /** @var bool Include latest = 1 in select query */
    protected $selectLatest = true;

    /**
     * Search constructor.
     */
    public function __construct()
    {
        /** @var  $dbHelper */
        $this->dbHelper = plugin_load('helper', 'struct_db');
        $this->sqlite = $this->dbHelper->getDB();
    }

    public function getDb()
    {
        return $this->sqlite;
    }

    /**
     * Add a schema to be searched
     *
     * Call multiple times for multiple schemas.
     *
     * @param string $table
     * @param string $alias
     */
    public function addSchema($table, $alias = '')
    {
        $schema = new Schema($table);
        if (!$schema->getId()) {
            throw new StructException('schema missing', $table);
        }

        $this->schemas[$schema->getTable()] = $schema;
        if ($alias) $this->aliases[$alias] = $schema->getTable();
    }

    /**
     * Add a column to be returned by the search
     *
     * Call multiple times for multiple columns. Be sure the referenced tables have been
     * added before
     *
     * @param string $colname may contain an alias
     */
    public function addColumn($colname)
    {
        if ($this->processWildcard($colname)) return; // wildcard?
        if ($colname[0] == '-') { // remove column from previous wildcard lookup
            $colname = substr($colname, 1);
            foreach ($this->columns as $key => $col) {
                if ($col->getLabel() === $colname) unset($this->columns[$key]);
            }
            return;
        }

        $col = $this->findColumn($colname);
        if (!$col) return; //FIXME do we really want to ignore missing columns?
        $this->columns[] = $col;
    }

    /**
     * Add sorting options
     *
     * Call multiple times for multiple columns. Be sure the referenced tables have been
     * added before
     *
     * @param string $colname may contain an alias
     * @param bool $asc sort direction (ASC = true, DESC = false)
     * @param bool $nc set true for caseinsensitivity
     */
    public function addSort($colname, $asc = true, $nc = true)
    {
        $col = $this->findColumn($colname);
        if (!$col) return; //FIXME do we really want to ignore missing columns?

        $this->sortby[$col->getFullQualifiedLabel()] = [$col, $asc, $nc];
    }

    /**
     * Clear all sorting options
     *
     * @return void
     */
    public function clearSort()
    {
        $this->sortby = [];
    }

    /**
     * Returns all set sort columns
     *
     * @return array
     */
    public function getSorts()
    {
        return $this->sortby;
    }

    /**
     * Adds a filter
     *
     * @param string $colname may contain an alias
     * @param string|string[] $value
     * @param string $comp @see self::COMPARATORS
     * @param string $op either 'OR' or 'AND'
     */
    public function addFilter($colname, $value, $comp, $op = 'OR')
    {
        $filter = $this->createFilter($colname, $value, $comp, $op);
        if ($filter) $this->filter[] = $filter;
    }

    /**
     * Adds a dynamic filter
     *
     * @param string $colname may contain an alias
     * @param string|string[] $value
     * @param string $comp @see self::COMPARATORS
     * @param string $op either 'OR' or 'AND'
     */
    public function addDynamicFilter($colname, $value, $comp, $op = 'OR')
    {
        $filter = $this->createFilter($colname, $value, $comp, $op);
        if ($filter) $this->dynamicFilter[] = $filter;
    }

    /**
     * Create a filter definition
     *
     * @param string $colname may contain an alias
     * @param string|string[] $value
     * @param string $comp @see self::COMPARATORS
     * @param string $op either 'OR' or 'AND'
     * @return array|null [Column col, string|string[] value, string comp, string op]
     */
    protected function createFilter($colname, $value, $comp, $op = 'OR')
    {
        /* Convert certain filters into others
         * this reduces the number of supported filters to implement in types */
        if ($comp == '*~') {
            $value = $this->filterWrapAsterisks($value);
            $comp = '~';
        } elseif ($comp == '<>') {
            $comp = '!=';
        }

        if (!in_array($comp, self::$COMPARATORS))
            throw new StructException("Bad comperator. Use " . implode(',', self::$COMPARATORS));
        if ($op != 'OR' && $op != 'AND')
            throw new StructException('Bad filter type . Only AND or OR allowed');

        $col = $this->findColumn($colname);
        if (!$col) return null; // ignore missing columns, filter might have been for different schema

        // map filter operators to SQL syntax
        switch ($comp) {
            case '~':
                $comp = 'LIKE';
                break;
            case '!~':
                $comp = 'NOT LIKE';
                break;
            case '=*':
                $comp = 'REGEXP';
                break;
        }

        // we use asterisks, but SQL wants percents
        if ($comp == 'LIKE' || $comp == 'NOT LIKE') {
            $value = $this->filterChangeToLike($value);
        }

        if ($comp == ' IN ' && !is_array($value)) {
            $value = $this->parseFilterValueList($value);
            //col IN ('a', 'b', 'c') is equal to col = 'a' OR 'col = 'b' OR col = 'c'
            $comp = '=';
        }

        // add the filter
        return [$col, $value, $comp, $op];
    }

    /**
     * Parse SQLite row value into array
     *
     * @param string $value
     * @return string[]
     */
    protected function parseFilterValueList($value)
    {
        $Handler = new FilterValueListHandler();
        $LexerClass = class_exists('\Doku_Lexer') ? '\Doku_Lexer' : '\dokuwiki\Parsing\Lexer\Lexer';
        $isLegacy = $LexerClass === '\Doku_Lexer';
        /** @var \Doku_Lexer|Lexer $Lexer */
        $Lexer = new $LexerClass($Handler, 'base', true);


        $Lexer->addEntryPattern('\(', 'base', 'row');
        $Lexer->addPattern('\s*,\s*', 'row');
        $Lexer->addExitPattern('\)', 'row');

        $Lexer->addEntryPattern('"', 'row', 'double_quote_string');
        $Lexer->addSpecialPattern('\\\\"', 'double_quote_string', 'escapeSequence');
        $Lexer->addExitPattern('"', 'double_quote_string');

        $Lexer->addEntryPattern("'", 'row', 'singleQuoteString');
        $Lexer->addSpecialPattern("\\\\'", 'singleQuoteString', 'escapeSequence');
        $Lexer->addExitPattern("'", 'singleQuoteString');

        $Lexer->mapHandler('double_quote_string', 'singleQuoteString');

        $Lexer->addSpecialPattern('[-+]?[0-9]*\.?[0-9]+(?:[eE][-+]?[0-9]+)?', 'row', 'number');

        $res = $Lexer->parse($value);

        $currentMode = $isLegacy ? $Lexer->_mode->getCurrent() : $Lexer->getModeStack()->getCurrent();
        if (!$res || $currentMode != 'base') {
            throw new StructException('invalid row value syntax');
        }

        return $Handler->getRow();
    }

    /**
     * Wrap given value in asterisks
     *
     * @param string|string[] $value
     * @return string|string[]
     */
    protected function filterWrapAsterisks($value)
    {
        $map = static fn($input) => "*$input*";

        if (is_array($value)) {
            $value = array_map($map, $value);
        } else {
            $value = $map($value);
        }
        return $value;
    }

    /**
     * Change given string to use % instead of *
     *
     * @param string|string[] $value
     * @return string|string[]
     */
    protected function filterChangeToLike($value)
    {
        $map = static fn($input) => str_replace('*', '%', $input);

        if (is_array($value)) {
            $value = array_map($map, $value);
        } else {
            $value = $map($value);
        }
        return $value;
    }

    /**
     * Set offset for the results
     *
     * @param int $offset
     */
    public function setOffset($offset)
    {
        $limit = 0;
        if ($this->range_end) {
            // if there was a limit set previously, the range_end needs to be recalculated
            $limit = $this->range_end - $this->range_begin;
        }
        $this->range_begin = $offset;
        if ($limit) $this->setLimit($limit);
    }

    /**
     * Get the current offset for the results
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->range_begin;
    }

    /**
     * Limit results to this number
     *
     * @param int $limit Set to 0 to disable limit again
     */
    public function setLimit($limit)
    {
        if ($limit) {
            $this->range_end = $this->range_begin + $limit;
        } else {
            $this->range_end = 0;
        }
    }

    /**
     * Get the current limit for the results
     *
     * @return int
     */
    public function getLimit()
    {
        if ($this->range_end) {
            return $this->range_end - $this->range_begin;
        }
        return 0;
    }

    /**
     * Allows disabling default 'latest = 1' clause in select statement
     *
     * @param bool $selectLatest
     */
    public function setSelectLatest($selectLatest): void
    {
        $this->selectLatest = $selectLatest;
    }

    /**
     * If the search result object does not exist yet,
     * the search is run and the result object returned
     *
     * @return SearchResult
     */
    public function getResult()
    {
        if (is_null($this->result)) {
            $this->run();
        }
        return $this->result;
    }

    /**
     * Return the number of results (regardless of limit and offset settings)
     */
    public function getCount()
    {
        return $this->getResult()->getCount();
    }

    /**
     * Returns the PID associated with each result row
     */
    public function getPids()
    {
        return $this->getResult()->getPids();
    }

    /**
     * Returns the rid associated with each result row
     *
     * @return array
     */
    public function getRids()
    {
        return $this->getResult()->getRids();
    }

    /**
     * Returns the revisions of search results
     *
     * @return array
     */
    public function getRevs()
    {
        return $this->getResult()->getRevs();
    }

    /**
     * Returns the actual result rows
     *
     * @return Value[][]
     */
    public function getRows()
    {
        return $this->getResult()->getRows();
    }

    /**
     * Execute this search and return the result
     *
     * The result is a two dimensional array of Value()s.
     *
     * This will always query for the full result (not using offset and limit) and then
     * return the wanted range, setting the count to the whole result number
     *
     * @deprecated Use getRows() instead
     * @return Value[][]
     */
    public function execute()
    {
        DebugHelper::dbgDeprecatedFunction(\dokuwiki\plugin\struct\meta\Search::class . '::getRows()');
        return $this->getRows();
    }

    /**
     * Run the actual search and populate the result object
     *
     * @return void
     */
    protected function run()
    {
        [$sql, $opts] = $this->getSQL();

        /** @var \PDOStatement $res */
        $res = $this->sqlite->query($sql, $opts);
        if ($res === false) throw new StructException("SQL execution failed for\n\n$sql");

        $pageidAndRevOnly = array_reduce(
            $this->columns,
            static fn($pageidAndRevOnly, Column $col) => $pageidAndRevOnly && ($col->getTid() == 0),
            true
        );

        $this->result = new SearchResult($res, $this->range_begin, $this->range_end, $this->columns, $pageidAndRevOnly);
        $res->closeCursor();
    }

    /**
     * Transform the set search parameters into a statement
     *
     * Calls runSQLBuilder()
     *
     * @return array ($sql, $opts) The SQL and parameters to execute
     */
    public function getSQL()
    {
        if (!$this->columns) throw new StructException('nocolname');
        return $this->runSQLBuilder()->getSQL();
    }

    /**
     * Initialize and execute the SQLBuilder
     *
     * Called from getSQL(). Can be overwritten to extend the query using the query builder
     *
     * @return SearchSQLBuilder
     */
    protected function runSQLBuilder()
    {
        $sqlBuilder = new SearchSQLBuilder();
        $sqlBuilder->setSelectLatest($this->selectLatest);
        $sqlBuilder->addSchemas($this->schemas);
        $sqlBuilder->addColumns($this->columns);
        $sqlBuilder->addFilters($this->filter);
        $sqlBuilder->addFilters($this->dynamicFilter);
        $sqlBuilder->addSorts($this->sortby);
        return $sqlBuilder;
    }

    /**
     * Returns all the columns that where added to the search
     *
     * @return Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * All the schemas currently added
     *
     * @return Schema[]
     */
    public function getSchemas()
    {
        return array_values($this->schemas);
    }

    /**
     * Checks if the given column is a * wildcard
     *
     * If it's a wildcard all matching columns are added to the column list, otherwise
     * nothing happens
     *
     * @param string $colname
     * @return bool was wildcard?
     */
    protected function processWildcard($colname)
    {
        [$colname, $table] = $this->resolveColumn($colname);
        if ($colname !== '*') return false;

        // no table given? assume the first is meant
        if ($table === null) {
            $schema_list = array_keys($this->schemas);
            $table = $schema_list[0];
        }

        $schema = $this->schemas[$table] ?? null;
        if (!$schema instanceof Schema) return false;
        $this->columns = array_merge($this->columns, $schema->getColumns(false));
        return true;
    }

    /**
     * Split a given column name into table and column
     *
     * Handles Aliases. Table might be null if none given.
     *
     * @param $colname
     * @return array (colname, table)
     */
    protected function resolveColumn($colname)
    {
        if (!$this->schemas) throw new StructException('noschemas');

        // resolve the alias or table name
        [$table, $colname] = sexplode('.', $colname, 2, '');
        if (!$colname) {
            $colname = $table;
            $table = null;
        }
        if ($table && isset($this->aliases[$table])) {
            $table = $this->aliases[$table];
        }

        if (!$colname) throw new StructException('nocolname');

        return [$colname, $table];
    }

    /**
     * Find a column to be used in the search
     *
     * @param string $colname may contain an alias
     * @return bool|Column
     */
    public function findColumn($colname, $strict = false)
    {
        if (!$this->schemas) throw new StructException('noschemas');
        $schema_list = array_keys($this->schemas);

        // add "fake" column for special col
        if ($colname == '%pageid%') {
            return new PageColumn(0, new Page(), $schema_list[0]);
        }
        if ($colname == '%title%') {
            return new PageColumn(0, new Page(['usetitles' => true]), $schema_list[0]);
        }
        if ($colname == '%lastupdate%') {
            return new RevisionColumn(0, new DateTime(), $schema_list[0]);
        }
        if ($colname == '%lasteditor%') {
            return new UserColumn(0, new User(), $schema_list[0]);
        }
        if ($colname == '%lastsummary%') {
            return new SummaryColumn(0, new AutoSummary(), $schema_list[0]);
        }
        if ($colname == '%rowid%') {
            return new RowColumn(0, new Decimal(), $schema_list[0]);
        }
        if ($colname == '%published%') {
            return new PublishedColumn(0, new Decimal(), $schema_list[0]);
        }

        [$colname, $table] = $this->resolveColumn($colname);

        /*
         * If table name is given search only that, otherwise if no strict behavior
         * is requested by the caller, try all assigned schemas for matching the
         * column name.
         */
        if ($table !== null && isset($this->schemas[$table])) {
            $schemas = [$table => $this->schemas[$table]];
        } elseif ($table === null || !$strict) {
            $schemas = $this->schemas;
        } else {
            return false;
        }

        // find it
        $col = false;
        foreach ($schemas as $schema) {
            if (empty($schema)) {
                continue;
            }
            $col = $schema->findColumn($colname);
            if ($col) break;
        }

        return $col;
    }
}
