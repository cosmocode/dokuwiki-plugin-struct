<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\Color;

/**
 * @group plugin_struct
 * @group plugins
 */
class ColorTest extends StructTest
{

    /**
     * DataProvider for successful validations
     */
    public function validate_success()
    {
        return [
            ['#123abc', '#123abc'],
            ['#123abc ', '#123abc'],
            [' #123abc', '#123abc'],
            [' #123abc ', '#123abc'],

            ['#123EDF', '#123edf'],
            ['#123EDF ', '#123edf'],
            [' #123EDF', '#123edf'],
            [' #123EDF ', '#123edf'],

            ['#ffffff', ''],
            [' #ffffff', ''],
            ['#ffffff ', ''],
            [' #ffffff ', ''],
        ];
    }

    /**
     * @dataProvider validate_success
     */
    public function test_validation_success($input, $expect)
    {
        $date = new Color();

        $this->assertEquals($expect, $date->validate($input));
    }


    /**
     * DataProvider for failed validations
     */
    public function validate_fail()
    {
        return [
            ['ffffff'],
            ['foo bar'],
            ['#ccc'],
        ];
    }

    /**
     * @dataProvider validate_fail
     */
    public function test_validation_fail($input)
    {
        $this->expectException(ValidationException::class);
        $date = new Color();

        $date->validate($input);
    }
}
