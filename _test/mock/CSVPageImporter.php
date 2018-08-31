<?php


namespace dokuwiki\plugin\struct\test\mock;


class CSVPageImporter extends \dokuwiki\plugin\struct\meta\CSVPageImporter
{

    /** @var \Generator */
    protected $testData;

    public function setTestData(array $testData)
    {
        $this->testData = $this->testDataGenerator($testData);

    }

    protected function openFile($file)
    {
    }

    protected function getLine()
    {
        if (!$this->testData->valid()) {
            return $this->testData->getReturn();
        }
        $current = $this->testData->current();
        $this->testData->next();
        return $current;
    }

    protected function testDataGenerator($testData)
    {
        foreach ($testData as $line) {
            yield $line;
        }

        return false;
    }
}
