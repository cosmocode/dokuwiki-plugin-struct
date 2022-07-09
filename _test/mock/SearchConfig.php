<?php

namespace dokuwiki\plugin\struct\test\mock;

use dokuwiki\plugin\struct\meta;

class SearchConfig extends meta\SearchConfig
{
    public function applyFilterVars($filter, $isMetadataRender = false)
    {
        return parent::applyFilterVars($filter, $isMetadataRender);
    }

    public function determineCacheFlag($filters)
    {
        return parent::determineCacheFlag($filters);
    }

}
