<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\TranslationUtilities;
use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\meta\Value;

/**
 * Class AbstractBaseType
 *
 * This class represents a basic type that can be configured to be used in a Schema. It is the main
 * part of a column definition as defined in meta\Column
 *
 * This defines also how the content of the coulmn will be entered and formatted.
 *
 * @package dokuwiki\plugin\struct\types
 * @see Column
 */
abstract class AbstractBaseType
{
    use TranslationUtilities;

    /**
     * @var array current config
     */
    protected $config = [];

    /**
     * @var array config keys that should not be cleaned despite not being in $config
     */
    protected $keepconfig = ['label', 'hint', 'visibility'];

    /**
     * @var string label for the field
     */
    protected $label = '';

    /**
     * @var bool is this a multivalue field?
     */
    protected $ismulti = false;

    /**
     * @var int the type ID
     */
    protected $tid = 0;

    /**
     * @var null|Column the column context this type is part of
     */
    protected $context;

    /**
     * @var Plugin
     */
    protected $hlp;

    /**
     * AbstractBaseType constructor.
     * @param array|null $config The configuration, might be null if nothing saved, yet
     * @param string $label The label for this field (empty for new definitions=
     * @param bool $ismulti Should this field accept multiple values?
     * @param int $tid The id of this type if it has been saved, yet
     */
    public function __construct($config = null, $label = '', $ismulti = false, $tid = 0)
    {
        // general config options
        $baseconfig = [
            'visibility' => [
                'inpage' => true,
                'ineditor' => true
            ]
        ];

        // use previously saved configuration, ignoring all keys that are not supposed to be here
        if (!is_null($config)) {
            $this->mergeConfig($config, $this->config);
        }

        $this->initTransConfig();
        $this->config = array_merge($baseconfig, $this->config);
        $this->label = $label;
        $this->ismulti = (bool)$ismulti;
        $this->tid = $tid;
    }

    /**
     * Merge the current config with the base config of the type
     *
     * Ignores all keys that are not supposed to be there. Recurses into sub keys
     *
     * @param array $current Current configuration
     * @param array $config Base Type configuration
     */
    protected function mergeConfig($current, &$config)
    {
        foreach ($current as $key => $value) {
            if (isset($config[$key]) || in_array($key, $this->keepconfig)) {
                if (isset($config[$key]) && is_array($config[$key])) {
                    $this->mergeConfig($value, $config[$key]);
                } else {
                    $config[$key] = $value;
                }
            }
        }
    }

    /**
     * Returns data as associative array
     *
     * @return array
     */
    public function getAsEntry()
    {
        return [
            'config' => json_encode($this->config),
            'label' => $this->label,
            'ismulti' => $this->ismulti,
            'class' => $this->getClass()
        ];
    }

    /**
     * The class name of this type (no namespace)
     * @return string
     */
    public function getClass()
    {
        $class = get_class($this);
        return substr($class, strrpos($class, "\\") + 1);
    }

    /**
     * Return the current configuration for this type
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return boolean
     */
    public function isMulti()
    {
        return $this->ismulti;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Returns the translated label for this type
     *
     * Uses the current language as determined by $conf['lang']. Falls back to english
     * and then to the type label
     *
     * @return string
     */
    public function getTranslatedLabel()
    {
        return $this->getTranslatedKey('label', $this->label);
    }

    /**
     * Returns the translated hint for this type
     *
     * Uses the current language as determined by $conf['lang']. Falls back to english.
     * Returns empty string if no hint is configured
     *
     * @return string
     */
    public function getTranslatedHint()
    {
        return $this->getTranslatedKey('hint', '');
    }

    /**
     * @return int
     */
    public function getTid()
    {
        return $this->tid;
    }

    /**
     * @return Column
     * @throws StructException
     */
    public function getContext()
    {
        if (is_null($this->context)) {
            throw new StructException(
                'Empty column context requested. Type was probably initialized outside of Schema.'
            );
        }
        return $this->context;
    }

    /**
     * @param Column $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @return bool
     */
    public function isVisibleInEditor()
    {
        return $this->config['visibility']['ineditor'];
    }

    /**
     * @return bool
     */
    public function isVisibleInPage()
    {
        return $this->config['visibility']['inpage'];
    }

    /**
     * Split a single value into multiple values
     *
     * This function is called on saving data when only a single value instead of an array
     * was submitted.
     *
     * Types implementing their own @param string $value
     * @return array
     * @see multiValueEditor() will probably want to override this
     *
     */
    public function splitValues($value)
    {
        return array_map('trim', explode(',', $value));
    }

    /**
     * Return the editor to edit multiple values
     *
     * Types can override this to provide a better alternative than multiple entry fields
     *
     * @param string $name the form base name where this has to be stored
     * @param string[] $rawvalues the current values
     * @param string $htmlID a unique id to be referenced by the label
     * @return string html
     */
    public function multiValueEditor($name, $rawvalues, $htmlID)
    {
        $html = '';
        foreach ($rawvalues as $value) {
            $html .= '<div class="multiwrap">';
            $html .= $this->valueEditor($name . '[]', $value, '');
            $html .= '</div>';
        }
        // empty field to add
        $html .= '<div class="newtemplate">';
        $html .= '<div class="multiwrap">';
        $html .= $this->valueEditor($name . '[]', '', $htmlID);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Return the editor to edit a single value
     *
     * @param string $name the form name where this has to be stored
     * @param string $rawvalue the current value
     * @param string $htmlID a unique id to be referenced by the label
     *
     * @return string html
     */
    public function valueEditor($name, $rawvalue, $htmlID)
    {
        $class = 'struct_' . strtolower($this->getClass());

        // support the autocomplete configurations out of the box
        if (isset($this->config['autocomplete']['maxresult']) && $this->config['autocomplete']['maxresult']) {
            $class .= ' struct_autocomplete';
        }

        $params = [
            'name' => $name,
            'value' => $rawvalue,
            'class' => $class,
            'id' => $htmlID
        ];
        $attributes = buildAttributes($params, true);
        return "<input $attributes>";
    }

    /**
     * Output the stored data
     *
     * @param string|int $value the value stored in the database
     * @param \Doku_Renderer $R the renderer currently used to render the data
     * @param string $mode The mode the output is rendered in (eg. XHTML)
     * @return bool true if $mode could be satisfied
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        $value = $this->displayValue($value);
        $R->cdata($value);
        return true;
    }

    /**
     * format and return the data
     *
     * @param int[]|string[] $values the values stored in the database
     * @param \Doku_Renderer $R the renderer currently used to render the data
     * @param string $mode The mode the output is rendered in (eg. XHTML)
     * @return bool true if $mode could be satisfied
     */
    public function renderMultiValue($values, \Doku_Renderer $R, $mode)
    {
        $len = count($values);
        for ($i = 0; $i < $len; $i++) {
            $this->renderValue($values[$i], $R, $mode);
            if ($i < $len - 1) {
                $R->cdata(', ');
            }
        }
        return true;
    }

    /**
     * Render a link in a struct cloud. This should be good for most types, but can be overwritten if necessary.
     *
     * @param string|int $value the value stored in the database
     * @param \Doku_Renderer $R the renderer currently used to render the data
     * @param string $mode The mode the output is rendered in (eg. XHTML)
     * @param string $page the target to which should be linked
     * @param string $filter the filter to apply to the aggregations on $page
     * @param int $weight the scaled weight of the item. implemented as css font-size on the outside container
     * @param int $showCount count for the tag, only passed if summarize was set in config
     */
    public function renderTagCloudLink($value, \Doku_Renderer $R, $mode, $page, $filter, $weight, $showCount)
    {
        $value = $this->displayValue($value);
        if ($showCount) {
             $value .= " ($showCount)";
        }
        $R->internallink("$page?$filter", $value);
    }

    /**
     * Creates a JOIN to handle multi-valued columns.
     *
     * @param QueryBuilder $QB
     * @param string $datatable The table for the schema in question
     * @param string $multitable The table containing multi-valued data for this schema
     * @param int $colref The ID of the multi-valued column
     * @param bool $test_rid Whether to require RIDs to be equal in the JOIN condition
     * @return string Alias for the multi-table
     */
    public function joinMulti(QueryBuilder $QB, $datatable, $multitable, $colref, $test_rid = true)
    {
        $MN = $QB->generateTableAlias('M');
        $condition = "$datatable.pid = $MN.pid ";
        if ($test_rid) $condition .= "AND $datatable.rid = $MN.rid ";
        $condition .= "AND $datatable.rev = $MN.rev AND $MN.colref = $colref";
        $QB->addLeftJoin(
            $datatable,
            $multitable,
            $MN,
            $condition
        );
        return $MN;
    }

    /**
     * This function is used to modify an aggregation query to add a filter
     * for the given column matching the given value. A type should add at
     * least a filter here but could do additional things like joining more
     * tables needed to handle more complex filters
     *
     * Important: $value might be an array. If so, the filter should check against
     * all provided values ORed together
     *
     * @param QueryBuilderWhere $add The where clause where statements can be added
     * @param string $tablealias The table the currently saved value(s) are stored in
     * @param string $colname The column name on above table to use in the SQL
     * @param string $comp The SQL comparator (LIKE, NOT LIKE, =, !=, etc)
     * @param string|string[] $value this is the user supplied value to compare against. might be multiple
     * @param string $op the logical operator this filter should use (AND|OR)
     */
    public function filter(QueryBuilderWhere $add, $tablealias, $colname, $comp, $value, $op)
    {
        $compareVal = $this->getSqlCompareValue($add, $tablealias, $colname, $op);
        /** @var QueryBuilderWhere $add Where additionional queries are added to */
        if (is_array($value)) {
            $add = $add->where($op); // sub where group
            $op = 'OR';
        }
        foreach ((array)$value as $item) {
            if (is_array($compareVal)) {
                $sub = $add->where($op);
                $op = 'OR'; // safe to do, as if the previous line is
                            // executed again it means $value is an
                            // array and $op was already 'OR' anyway
            } else {
                $sub = $add;
            }
            $pl = $this->getSqlConstantValue($add->getQB()->addValue($item));
            foreach ((array)$compareVal as $lhs) {
                $sub->where($op, "$lhs $comp $pl");
            }
        }
    }

    /**
     * This function provides the SQL expression for this column which is used to
     * compare against in a filter expression or a JOIN condition. In simple cases
     * that is all it will need to do. However, for some columnt types, it may
     * need to add additional logic to the conditional expression or make
     * additional JOINs.
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string $colname The column name on the above table
     * @param string &$op the logical operator this filter should use
     * @return string|array The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias, $colname, &$op)
    {
        return "$tablealias.$colname";
    }

    /**
     * Handle the value that a column is being compared against. In
     * most cases this method will just return the value unchanged,
     * but for some types it may be necessary to preform some sort of
     * transformation (e.g., casting it to a decimal).
     *
     * @param string $value The value a column is being compared to
     * @return string A SQL expression processing the value in some way.
     */
    protected function getSqlConstantValue($value)
    {
        return $value;
    }

    /**
     * Returns a SQL expression ON which JOIN $left_table and
     * $right_table.  Semantically, this provides an
     * equality comparison between two columns in the two
     * schemas. However, in practice it may require more complex
     * logic, including additional JOINs to pull in other data or
     * handle multi-valued columns.
     *
     * @param QueryBuilder $QB
     * @param string $left_table The name of the left table being JOINed
     * @param string $left_colname The name of the column in the left table being compared against for the JOIN
     * @param string $right_table The name of the right table being JOINed
     * @param string $right_colname The name of hte column in the right table being compared against for hte JOIN
     * @param AbstractBaseType $right_coltype The type of $right_colname
     * @return string SQL expression on which to join schemas
     */
    public function joinCondition($QB, $left_table, $left_colname, $right_table, $right_colname, $right_coltype)
    {
        $add = new QueryBuilderWhere($QB);
        $op = 'AND';
        $lhs = $this->getSqlCompareValue($add, $left_table, $left_colname, $op);
        $rhs = $this->getSqlConstantValue(
            $right_coltype->getSqlCompareValue($add, $right_table, $right_colname, $op)
        );
        // FIXME: Need to handle possibility of getSqlCompareValue returning multiple
        //        values (i.e., due to joining on page name)
        // FIXME: Need to consider how/whether to handle multi-valued columns
        $AN = $add->getQB()->generateTableAlias('A');
        $subquery = "(SELECT assigned
                     FROM schema_assignments AS $AN
                     WHERE $left_table.pid != '' AND
                           $left_table.pid = $AN.pid AND
                           $AN.tbl = '{$this->getContext()->getTable()}')";
        $subAnd = $add->whereSubAnd();
        $subAnd->whereOr("$left_table.pid = ''");
        $subOr = $subAnd->whereSubOr();
        $subOr->whereAnd("GETACCESSLEVEL($left_table.pid) > 0");
        $subOr->whereAnd("PAGEEXISTS($left_table.pid) = 1");
        $subOr->whereAnd("($subquery = 1 OR $subquery IS NULL)");
        return "$lhs = $rhs";
    }

    /**
     * Returns an expression for one side of the equality-comparison
     * used when JOINing schemas in aggregations. It may add
     * additional conditions to the $add expression or JOIN other
     * tables, as needed.
     *
     * @param QueryBuilderWhere $add The condition ON which to JOIN the tables. May not be used.
     * @param string $table Name of the table being JOINed
     * @param string $colname Name of the column being JOINed ON
     * @return string One side of the equality comparion being used for the JOIN
     */
    protected function joinArgument(QueryBuilderWhere $add, $table, $colname)
    {
        return "$table.$colname";
    }

    /**
     * Add the proper selection for this type to the current Query. Handles the
     * possibility of multi-valued columns.
     *
     * @param QueryBuilder $QB
     * @param string $singletable The name of the table the saved value(s) are stored in, if the column is single-valued
     * @param string $multitable The name of the table the values are stored in if the column is multi-valued
     * @param string $alias The added selection *has* to use this column alias
     * @param bool $test_rid Whether to require RIDs to be equal if JOINing multi-table
     * @param string|null $concat_sep Seperator to concatenate mutli-values together. Don't concatenate if null.
     */
    public function select(QueryBuilder $QB, $singletable, $multitable, $alias, $test_rid = true, $concat_sep = null)
    {
        if ($this->isMulti()) {
            $colref = $this->getContext()->getColref();
            $datatable = $this->joinMulti($QB, $singletable, $multitable, $colref, $test_rid);
            $colname = 'value';
        } else {
            $datatable = $singletable;
            $colname = $this->getContext()->getColName();
        }
        $this->selectCol($QB, $datatable, $colname, $alias);
        if ($this->isMulti()) {
            if (!is_null($concat_sep)) {
                $sel = $QB->getSelectStatement($alias);
                $QB->addSelectStatement("GROUP_CONCAT_DISTINCT($sel, '$concat_sep')", $alias);
            }
        } else {
            $QB->addGroupByStatement($alias);
        }
    }

    /**
     * Internal function to add the proper selection for a column of this type to the
     * current Query. It is called from the `select` method, after any joins needed
     * for multi-valued tables are handled.
     *
     * The default implementation here should be good for nearly all types, it simply
     * passes the given parameters to the query builder. But type may do more fancy
     * stuff here, eg. join more tables or select multiple values and combine them to
     * JSON. If you do, be sure implement a fitting rawValue() method.
     *
     * The passed $tablealias.$columnname might be a data_* table (referencing a single
     * row) or a multi_* table (referencing multiple rows). In the latter case the
     * multi table has already been joined with the proper conditions.
     *
     * You may assume a column alias named 'PID' to be available, should you need the
     * current page context for a join or sub select.
     *
     * @param QueryBuilder $QB
     * @param string $tablealias The table the currently saved value(s) are stored in
     * @param string $colname The column name on above table
     * @param string $alias The added selection *has* to use this column alias
     */
    protected function selectCol(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        $QB->addSelectColumn($tablealias, $colname, $alias);
    }

    /**
     * Sort results by this type
     *
     * The default implementation should be good for nearly all types. However some
     * types may need to do proper SQLite type casting to have the right order.
     *
     * Generally if you implemented @param QueryBuilder $QB
     * @param string $tablealias The table the currently saved value is stored in
     * @param string $colname The column name on above table (always single column!)
     * @param string $order either ASC or DESC
     * @see selectCol() you probably want to implement this,
     * too.
     *
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        $QB->addOrderBy("$tablealias.$colname COLLATE NOCASE $order");
    }

    /**
     * Get the string by which to sort values of this type
     *
     * This implementation is designed to work both as registered function in sqlite
     * and to provide a string to be used in sorting values of this type in PHP.
     *
     * @param string|Value $value The string by which the types would usually be sorted
     *
     * @return string
     */
    public function getSortString($value)
    {
        if (is_string($value)) {
            return $value;
        }
        $display = $value->getDisplayValue();
        if (is_array($display)) {
            return blank($display[0]) ? "" : $display[0];
        }
        return $display;
    }

    /**
     * This allows types to apply a transformation to the value read by select()
     *
     * The returned value should always be a single, non-complex string. In general
     * it is the identifier a type stores in the database.
     *
     * This value will be used wherever the raw saved data is needed for comparisons.
     * The default implementations of renderValue() and valueEditor() will call this
     * function as well.
     *
     * @param string $value The value as returned by select()
     * @return string The value as saved in the database
     */
    public function rawValue($value)
    {
        return $value;
    }

    /**
     * This is called when a single string is needed to represent this Type's current
     * value as a single (non-HTML) string. Eg. in a dropdown or in autocompletion.
     *
     * @param string $value
     * @return string
     */
    public function displayValue($value)
    {
        return $this->rawValue($value);
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
        return $this->rawValue($value);
    }

    /**
     * Validate and optionally clean a single value
     *
     * This function needs to throw a validation exception when validation fails.
     * The exception message will be prefixed by the appropriate field on output
     *
     * The function should return the value as it should be saved later on.
     *
     * @param string|int $rawvalue
     * @return int|string the cleaned value
     * @throws ValidationException
     */
    public function validate($rawvalue)
    {
        return trim($rawvalue);
    }

    /**
     * Overwrite to handle Ajax requests
     *
     * A call to DOKU_BASE/lib/exe/ajax.php?call=plugin_struct&column=schema.name will
     * be redirected to this function on a fully initialized type. The result is
     * JSON encoded and returned to the caller. Access additional parameter via $INPUT
     * as usual
     *
     * @return mixed
     * @throws StructException when something goes wrong
     */
    public function handleAjax()
    {
        throw new StructException('not implemented');
    }

    /**
     * Convenience method to access plugin language strings
     *
     * @param string $string
     * @return string
     */
    public function getLang($string)
    {
        if (is_null($this->hlp)) $this->hlp = plugin_load('helper', 'struct');
        return $this->hlp->getLang($string);
    }

    /**
     * With what comparator should dynamic filters filter this type?
     *
     * This default does a LIKE operation
     *
     * @return string
     * @see Search::$COMPARATORS
     */
    public function getDefaultComparator()
    {
        return '*~';
    }
}
