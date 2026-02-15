<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => 'password',
                'role_slug' => 'admin',
            ],
            [
                'name' => 'Manager User',
                'username' => 'manager',
                'email' => 'manager@example.com',
                'password' => 'password',
                'role_slug' => 'manager',
            ],
            [
                'name' => 'Staff User',
                'username' => 'staff',
                'email' => 'staff@example.com',
                'password' => 'password',
                'role_slug' => 'staff',
            ],
            [
                'name' => 'Customer User',
                'username' => 'customer',
                'email' => 'customer@example.com',
                'password' => 'password',
                'role_slug' => 'customer',
            ],
        ];

        foreach ($users as $userData) {
            $role = Role::where('slug', $userData['role_slug'])->first();

            User::firstOrCreate(
                ['username' => $userData['username']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => $userData['password'],
                    'role_id' => $role?->id,
                ]
            );
        }
    }
}
