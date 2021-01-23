<?php

namespace dokuwiki\plugin\struct\test\mock;

class AccessTablePage extends \dokuwiki\plugin\struct\meta\AccessTablePage {

    public function getDataFromDB() {
        return parent::getDataFromDB();
    }

    public function buildGetDataSQL($idColumn = 'pid') {
        return parent::buildGetDataSQL($idColumn);
    }
}
