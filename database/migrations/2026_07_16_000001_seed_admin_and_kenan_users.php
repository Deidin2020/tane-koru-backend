<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->provisionUser(
                name: 'admin',
                email: 'admin@app.nemayapi.com.tr',
                password: 'admin123!@',
                role: 'admin',
                isSalesperson: false,
            );

            $this->provisionUser(
                name: 'Kenan Khalaf',
                email: 'kenan.khalaf@app.nemayapi.com.tr',
                password: 'kenan123!@',
                role: 'salesperson',
                isSalesperson: true,
            );
        });
    }

    public function down(): void
    {
        // Keep provisioned application users intact when rolling back code.
    }

    private function provisionUser(
        string $name,
        string $email,
        string $password,
        string $role,
        bool $isSalesperson,
    ): void {
        $now = now();

        $roleId = DB::table('roles')->where('name', $role)->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $user = DB::table('users')->where('email', $email)->first();
        $userData = [
            'name' => $name,
            'password' => Hash::make($password),
            'is_active' => true,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($user) {
            DB::table('users')->where('id', $user->id)->update($userData);
            $userId = $user->id;
        } else {
            $userId = DB::table('users')->insertGetId($userData + [
                'email' => $email,
                'created_at' => $now,
            ]);
        }

        $profile = DB::table('profiles')->where('user_id', $userId)->first();
        $profileData = [
            'full_name' => $name,
            'email' => $email,
            'is_salesperson' => $isSalesperson,
            'is_active' => true,
            'is_default' => false,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($profile) {
            DB::table('profiles')->where('id', $profile->id)->update($profileData);
        } else {
            DB::table('profiles')->insert($profileData + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }

        DB::table('user_roles')->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => $now,
        ]);
    }
};
