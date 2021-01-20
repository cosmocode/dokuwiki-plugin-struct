<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;
use dokuwiki\plugin\struct\meta\Schema;

abstract class AccessTable extends meta\AccessTable {

    public static function getPageAccess($tablename, $pid, $ts = 0)
    {
        $schema = new Schema($tablename, $ts);
        return new AccessTablePage($schema, $pid, $ts, 0);
    }

    public static function getGlobalAccess($tablename, $rid = 0)
    {
        $schema = new Schema($tablename, 0);
        return new AccessTableGlobal($schema, '', 0, $rid);
    }

    /**
     * @param Schema $schema
     * @param int|string $pid
     * @param int $ts
     * @param int $rid
     * @return AccessTableGlobal|AccessTablePage
     *@deprecated
     */
    public static function bySchema(Schema $schema, $pid, $ts = 0, $rid = 0) {
        if (self::isTypePage($pid, $ts, $rid)) {
            return new AccessTablePage($schema, $pid, $ts, $rid);
        }
        return new AccessTableGlobal($schema, $pid, $ts, $rid);
    }

    /**
     * @param string $tablename
     * @param string $pid
     * @param int $ts
     * @param int $rid
     * @return meta\AccessTablePage|AccessTableGlobal|AccessTablePage
     *@deprecated
     */
    public static function byTableName($tablename, $pid, $ts = 0, $rid = 0) {
        $schema = new Schema($tablename, $ts);
        return self::bySchema($schema, $pid, $ts); // becuse we have a static call here we can not rely on inheritance
    }

}
