<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\plugin\struct\types\Decimal;

/**
 * Published Column
 */
class PublishedColumn extends PageColumn
{
    /** @noinspection PhpMissingParentConstructorInspection
     * @param int $sort
     * @param Decimal $type
     * @param string $table
     */
    public function __construct($sort, Decimal $type, $table)
    {
        if ($type->isMulti()) throw new StructException('PublishedColumns can not be multi value types!');
        Column::__construct($sort, $type, 0, true, $table);
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return '%published%';
    }

    /**
     * @param bool $enforceSingleColumn ignored
     * @return string
     */
    public function getColName($enforceSingleColumn = true)
    {
        return 'published';
    }

    /**
     * @return string preconfigured label
     */
    public function getTranslatedLabel()
    {
        /** @var \helper_plugin_struct_config $helper */
        $helper = plugin_load('helper', 'struct_config');
        return $helper->getLang('publishedlabel');
    }
}
