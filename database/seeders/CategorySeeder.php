<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Audio',
                'description' => 'Audio equipment and accessories',
            ],
            [
                'name' => 'Lighting',
                'description' => 'Lighting equipment and accessories',
            ],
            [
                'name' => 'Field Equipment',
                'description' => 'Field and outdoor equipment',
            ],
            [
                'name' => 'Furniture',
                'description' => 'Furniture and fixtures',
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Maintenance tools and supplies',
            ],
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and components',
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
