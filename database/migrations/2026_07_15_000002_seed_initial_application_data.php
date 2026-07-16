<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $roleIds = [];

            foreach (['admin', 'project_manager', 'sales_manager', 'salesperson', 'viewer'] as $roleName) {
                $roleId = DB::table('roles')->where('name', $roleName)->value('id');

                if (! $roleId) {
                    $roleId = DB::table('roles')->insertGetId([
                        'name' => $roleName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $roleIds[$roleName] = $roleId;
            }

            $project = DB::table('projects')->where('name', 'Tane Koru')->first();

            if ($project) {
                DB::table('projects')->where('id', $project->id)->update([
                    'is_default' => true,
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('projects')->insert([
                    'name' => 'Tane Koru',
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $email = 'admin@tanekoru.com';
            $admin = DB::table('users')->where('email', $email)->first();
            $adminData = [
                'name' => 'Admin User',
                'password' => Hash::make('Admin123456!'),
                'is_active' => true,
                'deleted_at' => null,
                'updated_at' => $now,
            ];

            if ($admin) {
                DB::table('users')->where('id', $admin->id)->update($adminData);
                $adminId = $admin->id;
            } else {
                $adminId = DB::table('users')->insertGetId($adminData + [
                    'email' => $email,
                    'created_at' => $now,
                ]);
            }

            $profile = DB::table('profiles')->where('user_id', $adminId)->first();
            $profileData = [
                'full_name' => 'Admin User',
                'email' => $email,
                'is_salesperson' => false,
                'is_active' => true,
                'is_default' => false,
                'deleted_at' => null,
                'updated_at' => $now,
            ];

            if ($profile) {
                DB::table('profiles')->where('id', $profile->id)->update($profileData);
            } else {
                DB::table('profiles')->insert($profileData + [
                    'user_id' => $adminId,
                    'created_at' => $now,
                ]);
            }

            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $adminId,
                'role_id' => $roleIds['admin'],
                'created_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        // Keep provisioned production data intact when rolling back code.
    }
};
