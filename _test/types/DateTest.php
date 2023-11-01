<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\Date;

/**
 * @group plugin_struct
 * @group plugins
 */
class DateTest extends StructTest
{

    /**
     * DataProvider for successful validations
     */
    public function validate_success()
    {
        return [
            ['2017-04-12', '2017-04-12'],
            ['2017-04-12 ', '2017-04-12'],
            [' 2017-04-12 ', '2017-04-12'],
            ['2017-04-12 10:11', '2017-04-12'],
            ['2017-04-12 10:11:12', '2017-04-12'],
            ['2017-04-12 whatever', '2017-04-12'],
            ['2017-4-3', '2017-04-03'],
            ['917-4-3', '917-04-03'],
        ];
    }

    /**
     * @dataProvider validate_success
     */
    public function test_validation_success($input, $expect)
    {
        $date = new Date();

        $this->assertEquals($expect, $date->validate($input));
    }


    /**
     * DataProvider for failed validations
     */
    public function validate_fail()
    {
        return [
            ['2017-02-31'],
            ['2017-13-31'],
        ];
    }

    /**
     * @dataProvider validate_fail
     */
    public function test_validation_fail($input)
    {
        $this->expectException(ValidationException::class);
        $date = new Date();

        $date->validate($input);
    }
}
