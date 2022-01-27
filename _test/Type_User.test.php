<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\types\User;

/**
 * Testing the User Type
 *
 * @group plugin_struct
 * @group plugins
 */
class Type_User_struct_test extends StructTest
{

    public function test_validate_fail()
    {
        $this->expectException(ValidationException::class);
        $user = new User();
        $user->validate('nosuchuser');
    }

    public function test_validate_success()
    {
        $user = new User();
        $user->validate('testuser');
        $this->assertTrue(true); // we simply check that no exceptions are thrown

        $user = new User(array('existingonly' => false));
        $user->validate('nosuchuser');
        $this->assertTrue(true); // we simply check that no exceptions are thrown
    }

    public function test_ajax()
    {
        global $INPUT;

        $user = new User(
            array(
                'autocomplete' => array(
                    'fullname' => true,
                    'mininput' => 2,
                    'maxresult' => 5,
                ),
            )
        );

        $INPUT->set('search', 'test');
        $this->assertEquals(array(array('label' => 'Arthur Dent [testuser]', 'value' => 'testuser')), $user->handleAjax());

        $INPUT->set('search', 'dent');
        $this->assertEquals(array(array('label' => 'Arthur Dent [testuser]', 'value' => 'testuser')), $user->handleAjax());

        $INPUT->set('search', 'd'); // under mininput
        $this->assertEquals(array(), $user->handleAjax());

        $user = new User(
            array(
                'autocomplete' => array(
                    'fullname' => false,
                    'mininput' => 2,
                    'maxresult' => 5,
                ),
            )
        );

        $INPUT->set('search', 'test');
        $this->assertEquals(array(array('label' => 'Arthur Dent [testuser]', 'value' => 'testuser')), $user->handleAjax());

        $INPUT->set('search', 'dent');
        $this->assertEquals(array(), $user->handleAjax());

        $user = new User(
            array(
                'autocomplete' => array(
                    'fullname' => false,
                    'mininput' => 2,
                    'maxresult' => 0,
                ),
            )
        );

        $INPUT->set('search', 'test');
        $this->assertEquals(array(), $user->handleAjax());
    }
}
