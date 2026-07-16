<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        collect(['admin', 'project_manager', 'sales_manager', 'salesperson', 'viewer'])
            ->each(fn (string $role) => Role::query()->firstOrCreate(['name' => $role]));

        Project::query()->firstOrCreate(
            ['name' => 'Tane Koru'],
            ['is_default' => true]
        );

        $this->call(AdminSeeder::class);
    }
}
