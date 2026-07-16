<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);

            $admin = User::withTrashed()->firstOrNew([
                'email' => 'admin@app.nemayapi.com.tr',
            ]);

            $admin->fill([
                'name' => 'Admin',
                'password' => 'Admin123@',
                'is_active' => true,
            ]);

            if ($admin->trashed()) {
                $admin->restore();
            }

            $admin->save();

            $profile = Profile::withTrashed()->firstOrNew([
                'user_id' => $admin->id,
            ]);

            $profile->fill([
                'full_name' => 'Admin',
                'email' => $admin->email,
                'is_salesperson' => false,
                'is_active' => true,
                'is_default' => false,
            ]);

            if ($profile->trashed()) {
                $profile->restore();
            }

            $profile->save();
            $admin->roles()->sync([$adminRole->id]);
        });
    }
}
