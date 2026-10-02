<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'John Arvin Malvar',
                'email' => 'johndoe@gmail.com',
                'password' => 'tambang1005',
                'is_admin' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'balogs'],
            [
                'name' => 'Byron Jester Manalo',
                'email' => 'byronjester.manalo@gmail.com',
                'password' => 'balogs0226',
                'is_admin' => false,
            ]
        );
    }
}
