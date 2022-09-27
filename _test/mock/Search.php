<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;

class Search extends meta\Search
{
    public $schemas = array();
    /** @var  meta\Column[] */
    public $columns = array();

    public $sortby = array();

    public $filter = array();

    /**
     * Register a dummy function that always returns false
     */
    public function isNotPublisher()
    {
        $this->dbHelper = new helper_plugin_struct_db;
        $this->sqlite->create_function('IS_PUBLISHER', [$this->dbHelper, 'IS_PUBLISHER'], -1);
    }
}
