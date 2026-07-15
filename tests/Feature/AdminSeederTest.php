<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_single_requested_admin_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()
            ->with(['profile', 'roles'])
            ->where('email', 'admin@app.nemayapi.com.tr')
            ->sole();

        $this->assertSame('Admin', $admin->name);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Admin123@', $admin->password));
        $this->assertSame('Admin', $admin->profile?->full_name);
        $this->assertSame(['admin'], $admin->roles->pluck('name')->all());
        $this->assertSame(1, User::query()->count());
    }
}
