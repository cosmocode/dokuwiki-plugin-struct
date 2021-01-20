<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\plugin\struct\types\DateTime;

/**
 * Class RevisionColumn
 *
 * Just like a column, but does not reference one of the col* data columns but the rev column.
 *
 * @package dokuwiki\plugin\struct\meta
 */
class RevisionColumn extends Column
{

    /**
     * PageColumn constructor.
     *
     * @param int $sort
     * @param DateTime $type
     * @param string $table
     */
    public function __construct($sort, DateTime $type, $table = '')
    {
        if ($type->isMulti()) throw new StructException('RevisionColumns can not be multi value types!');
        parent::__construct($sort, $type, 0, true, $table);
        $this->getType()->setContext($this);
    }

    public function getColref()
    {
        throw new StructException('Accessing the colref of a RevisionColumn makes no sense');
    }

    /**
     * @param bool $enforceSingleColumn ignored
     * @return string
     */
    public function getColName($enforceSingleColumn = true)
    {
        return 'rev';
    }

    /**
     * @param bool $enforceSingleColumn ignored
     * @return string
     */
    public function getFullColName($enforceSingleColumn = true)
    {
        $col = $this->getColName($enforceSingleColumn);
        if ($this->table) $col = 'data_' . $this->table . '.' . $col;
        return $col;
    }

    /**
     * @return string always '%lastupdate%'
     */
    public function getLabel()
    {
        return '%lastupdate%';
    }

    /**
     * @return string always '%lastupdate%'
     */
    public function getFullQualifiedLabel()
    {
        // There is only one pageid for each row because we JOIN on it
        // so we do not prefix it with the table
        return $this->getLabel();
    }

    /**
     * @return string preconfigured label
     */
    public function getTranslatedLabel()
    {
        /** @var \helper_plugin_struct_config $helper */
        $helper = plugin_load('helper', 'struct_config');
        return $helper->getLang('revisionlabel');
    }
}
