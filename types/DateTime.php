<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\DateFormatConverter;
use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\ValidationException;

class DateTime extends Date
{
    protected $config = [
        'format' => '', // filled by constructor
        'prefilltoday' => false,
        'pastonly' => false,
        'futureonly' => false,
    ];

    /**
     * DateTime constructor.
     *
     * @param array|null $config
     * @param string $label
     * @param bool $ismulti
     * @param int $tid
     */
    public function __construct($config = null, $label = '', $ismulti = false, $tid = 0)
    {
        global $conf;
        $this->config['format'] = DateFormatConverter::toDate($conf['dformat']);

        parent::__construct($config, $label, $ismulti, $tid);
    }

    /**
     * Return the editor to edit a single value
     *
     * @param string $name the form name where this has to be stored
     * @param string $rawvalue the current value
     * @param string $htmlID
     *
     * @return string html
     */
    public function valueEditor($name, $rawvalue, $htmlID)
    {
        if ($this->config['prefilltoday'] && !$rawvalue) {
            $rawvalue = date('Y-m-d\TH:i');
        }
        $rawvalue = str_replace(' ', 'T', $rawvalue);
        $params = [
            'name' => $name,
            'value' => $rawvalue,
            'class' => 'struct_datetime',
            'type' => 'datetime-local', // HTML5 datetime picker
            'id' => $htmlID,
        ];
        $attributes = buildAttributes($params, true);
        return "<input $attributes />";
    }

    /**
     * Validate a single value
     *
     * This function needs to throw a validation exception when validation fails.
     * The exception message will be prefixed by the appropriate field on output
     *
     * @param string|array $rawvalue
     * @return string
     * @throws ValidationException
     */
    public function validate($rawvalue)
    {
        $rawvalue = trim($rawvalue);
        [$date, $time] = array_pad(preg_split('/[ |T]/', $rawvalue, 2), 2, '');
        $date = trim($date);
        $time = trim($time);

        [$year, $month, $day] = explode('-', $date, 3);
        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            throw new ValidationException('invalid datetime format');
        }
        if ($this->config['pastonly'] && strtotime($rawvalue) > time()) {
            throw new ValidationException('pastonly');
        }
        if ($this->config['futureonly'] && strtotime($rawvalue) < time()) {
            throw new ValidationException('futureonly');
        }

        [$h, $m] = array_pad(explode(':', $time, 3), 2, ''); // drop seconds
        $h = (int)$h;
        $m = (int)$m;
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            throw new ValidationException('invalid datetime format');
        }

        return sprintf("%d-%02d-%02d %02d:%02d", $year, $month, $day, $h, $m);
    }

    /**
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     */
    public function selectCol(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        $col = "$tablealias.$colname";

        // when accessing the revision column we need to convert from Unix timestamp
        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\RevisionColumn')) {
            $rightalias = $QB->generateTableAlias();
            $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
            $col = "DATETIME($rightalias.lastrev, 'unixepoch', 'localtime')";
        }

        $QB->addSelectStatement($col, $alias);
    }


    /**
     * Handle case of a revision column, where you need to convert from a Unix
     * timestamp.
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string $colname The column name on the above table
     * @param string|null $oldalias A previous alias used for this table (only used by Page)
     * @param string &$op the logical operator this filter should use
     * @return string|array The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias, $oldalias, $colname, &$op)
    {
        $col = "$tablealias.$colname";

        // when accessing the revision column we need to convert from Unix timestamp
        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\RevisionColumn')) {
            $col = "DATETIME($tablealias.lastrev, 'unixepoch', 'localtime')";
        }

        return $col;
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
        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\RevisionColumn')) {
            $rightalias = $QB->generateTableAlias();
            return [$tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid"];
        }
        return parent::getAdditionalJoinForComparison($add, $tablealias, $colname);
    }

    /**
     * When sorting `%lastupdated%`, then sort the data from the `titles` table instead the `data_` table.
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        $col = "$tablealias.$colname";

        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\RevisionColumn')) {
            $rightalias = $QB->generateTableAlias();
            $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
            $col = "$rightalias.lastrev";
        }

        $QB->addOrderBy("$col $order");
    }
}
