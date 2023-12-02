<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\User;

/**
 * Testing the User Type
 *
 * @group plugin_struct
 * @group plugins
 */
class UserTest extends StructTest
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

        $user = new User(['existingonly' => false]);
        $user->validate('nosuchuser');
        $this->assertTrue(true); // we simply check that no exceptions are thrown
    }

    public function test_ajax()
    {
        global $INFO, $INPUT, $USERINFO;
        include(__DIR__ . '/../../conf/default.php');
        $default_allow_autocomplete = $conf['allow_username_autocomplete'];
        unset($conf);

        global $conf;
        $conf['plugin']['struct']['allow_username_autocomplete'] = $default_allow_autocomplete;
        $_SERVER['REMOTE_USER'] = 'john';
        $USERINFO['name'] = 'John Smith';
        $USERINFO['mail'] = 'john.smith@example.com';
        $USERINFO['grps'] = ['user', 'test'];
        //update info array
        $INFO['userinfo'] = $USERINFO;

        $user = new User(
            [
                'autocomplete' => [
                    'fullname' => true,
                    'mininput' => 2,
                    'maxresult' => 5,
                ],
            ]
        );

        $INPUT->set('search', 'test');
        $this->assertEquals([['label' => 'Arthur Dent [testuser]', 'value' => 'testuser']], $user->handleAjax());

        $INPUT->set('search', 'dent');
        $this->assertEquals([['label' => 'Arthur Dent [testuser]', 'value' => 'testuser']], $user->handleAjax());

        $INPUT->set('search', 'd'); // under mininput
        $this->assertEquals([], $user->handleAjax());

        // Check restrictions on who can access username data are respected
        $conf['plugin']['struct']['allow_username_autocomplete'] = 'john';
        $INPUT->set('search', 'dent');
        $this->assertEquals([['label' => 'Arthur Dent [testuser]', 'value' => 'testuser']], $user->handleAjax());

        $conf['plugin']['struct']['allow_username_autocomplete'] = '@user';
        $INPUT->set('search', 'dent');
        $this->assertEquals([['label' => 'Arthur Dent [testuser]', 'value' => 'testuser']], $user->handleAjax());

        $conf['plugin']['struct']['allow_username_autocomplete'] = '@not_in_group,not_this_user';
        $INPUT->set('search', 'dent');
        $this->assertEquals([], $user->handleAjax());

        $conf['plugin']['struct']['allow_username_autocomplete'] = $default_allow_autocomplete;

        $user = new User(
            [
                'autocomplete' => [
                    'fullname' => false,
                    'mininput' => 2,
                    'maxresult' => 5,
                ],
            ]
        );

        $INPUT->set('search', 'test');
        $this->assertEquals([['label' => 'Arthur Dent [testuser]', 'value' => 'testuser']], $user->handleAjax());

        $INPUT->set('search', 'dent');
        $this->assertEquals([], $user->handleAjax());

        $user = new User(
            [
                'autocomplete' => [
                    'fullname' => false,
                    'mininput' => 2,
                    'maxresult' => 0,
                ],
            ]
        );

        $INPUT->set('search', 'test');
        $this->assertEquals([], $user->handleAjax());
    }
}
