<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system access',
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Management level access',
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Staff level access',
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Customer access',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
