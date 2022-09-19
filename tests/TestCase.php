<?php

namespace Tests;

use App\Enums\RoleNameEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;


    /**
     * @return \App\Models\User
     */
    public function adminUser(User $user = null): User
    {
        $user = $user ?? User::factory()->create();
        Role::firstOrCreate(['name' => RoleNameEnum::ADMIN]);
        $user->assignRole(RoleNameEnum::ADMIN);

        return $user;
    }

}
