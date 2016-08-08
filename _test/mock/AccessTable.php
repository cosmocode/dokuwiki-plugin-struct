<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta;

abstract class AccessTable extends meta\AccessTable {

    /**
     * @param Schema $schema
     * @param int|string $pid
     * @return meta\SchemaLookupData|SchemaData
     */
    public static function bySchema(Schema $schema, $pid) {
        if($schema->isLookup()) {
            return new meta\SchemaLookupData($schema, $pid); // FIXME not mocked, yet
        } else {
            return new SchemaData($schema, $pid);
        }
    }

    public static function byTableName($tablename, $pid, $ts = 0) {
        $schema = new Schema($tablename, $ts);
        return self::bySchema($schema, $pid); // becuse we have a static call here we can not rely on inheritance
    }

}
