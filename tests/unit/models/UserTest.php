<?php
namespace tests\unit\models;

use app\models\User;
use Codeception\Test\Unit;

class UserTest extends Unit
{
    public function testFindUserById()
    {
        $this->assertNotEmpty($user = User::findIdentity(100));
        $this->assertEquals('admin', $user->username);

        $this->assertEmpty(User::findIdentity(999));
    }


    public function testFindUserByUsername()
    {
        $this->assertNotEmpty($user = User::findByUsername('admin'));
        $this->assertEmpty(User::findByUsername('not-admin'));
    }
}
