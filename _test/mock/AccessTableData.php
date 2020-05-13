<?php

namespace dokuwiki\plugin\struct\test\mock;

class AccessTableData extends \dokuwiki\plugin\struct\meta\AccessTableData {

    public function getDataFromDB() {
        return parent::getDataFromDB();
    }

    public function buildGetDataSQL($idColumn = 'pid') {
        return parent::buildGetDataSQL($idColumn);
    }
}
