<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'project_manager', 'sales_manager', 'salesperson', 'viewer'] as $name) {
            Role::query()->firstOrCreate(['name' => $name]);
        }

        Project::query()->create(['name' => 'Default project', 'is_default' => true]);
        $this->admin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->admin->profile()->create([
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::query()->where('name', 'admin')->value('id'), ['created_at' => now()]);
        $this->token = app(ApiTokenService::class)->issue($this->admin);
    }

    public function test_initial_admin_created_by_migration_can_log_in(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@tanekoru.com',
            'password' => 'Admin123456!',
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'admin@tanekoru.com')
            ->assertJsonStructure(['access_token']);
    }

    public function test_public_registration_is_disabled_and_admin_can_manage_users(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Public User',
            'email' => 'public@example.com',
            'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('error.code', 'REGISTRATION_DISABLED');

        $created = $this->withToken($this->token)->postJson('/api/v1/users', [
            'full_name' => 'Ahmed Ali',
            'email' => 'ahmed@example.com',
            'password' => 'temporary-password',
            'roles' => ['salesperson'],
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('full_name', 'Ahmed Ali')
            ->assertJsonPath('roles.0', 'salesperson');

        $userId = $created->json('id');
        $this->withToken($this->token)->patchJson("/api/v1/users/{$userId}", [
            'full_name' => 'Ahmed Updated',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('full_name', 'Ahmed Updated')
            ->assertJsonPath('is_active', false);

        $this->withToken($this->token)->deleteJson('/api/v1/users/'.$this->admin->id)
            ->assertConflict()
            ->assertJsonPath('error.code', 'CANNOT_DELETE_CURRENT_USER');
    }

    public function test_default_salesperson_is_required_and_is_applied_to_clients_and_visits(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/clients', [
            'client_name' => 'No Default',
            'lead_source' => 'direct',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'DEFAULT_SALESPERSON_REQUIRED');

        $salesperson = $this->withToken($this->token)->postJson('/api/v1/salespeople', [
            'full_name' => 'Sara Hassan',
            'email' => 'sara@example.com',
            'phone' => '+90 555',
            'is_active' => true,
        ])->assertCreated()->json();

        $this->withToken($this->token)->putJson("/api/v1/salespeople/{$salesperson['id']}/default")
            ->assertOk()
            ->assertJsonPath('is_default', true);

        $agency = $this->createAgency();

        $client = $this->withToken($this->token)->postJson('/api/v1/clients', [
            'client_name' => 'Client One',
            'lead_source' => 'direct',
            'offer_details' => 'Unit B-12, 30% down payment',
        ])->assertCreated()
            ->assertJsonPath('assigned_salesperson_id', $salesperson['id'])
            ->assertJsonPath('offer_details', 'Unit B-12, 30% down payment');

        $this->withToken($this->token)->postJson('/api/v1/project-visits', [
            'agency_id' => $agency['id'],
            'visit_date' => now()->toISOString(),
            'feedback' => 'Interested',
        ])->assertCreated()
            ->assertJsonPath('sales_rep_id', $salesperson['id'])
            ->assertJsonPath('contact_person', 'Agency Contact')
            ->assertJsonPath('phone', '+90 111');

        $this->withToken($this->token)->postJson('/api/v1/company-visits', [
            'agency_id' => $agency['id'],
            'visit_date' => now()->toISOString(),
            'category' => 'small_agency',
        ])->assertCreated()
            ->assertJsonPath('sales_rep_id', $salesperson['id'])
            ->assertJsonPath('contact_person', 'Agency Contact')
            ->assertJsonPath('address', 'Istanbul');

        $this->withToken($this->token)->getJson('/api/v1/reports/daily?range=today')
            ->assertOk()
            ->assertJsonPath('clients.0.id', $client->json('id'))
            ->assertJsonPath('clients.0.offer_details', 'Unit B-12, 30% down payment')
            ->assertJsonPath('company_visits.total', 1);

        $second = $this->withToken($this->token)->postJson('/api/v1/salespeople', [
            'full_name' => 'Second Rep',
            'is_active' => true,
        ])->assertCreated()->json();
        $this->withToken($this->token)->putJson("/api/v1/salespeople/{$second['id']}/default")
            ->assertOk()
            ->assertJsonPath('is_default', true);

        $this->assertSame(1, Profile::query()->where('is_default', true)->count());
        $this->assertFalse(Profile::query()->findOrFail($salesperson['id'])->is_default);
    }

    public function test_client_update_ignores_read_only_fields_from_the_client_resource(): void
    {
        $salesperson = Profile::query()->create([
            'full_name' => 'Default Rep',
            'is_salesperson' => true,
            'is_active' => true,
            'is_default' => true,
        ]);
        $agency = $this->createAgency();

        $client = $this->withToken($this->token)->postJson('/api/v1/clients', [
            'client_name' => 'Original Client',
            'lead_source' => 'agency',
            'agency_id' => $agency['id'],
            'assigned_salesperson_id' => $salesperson->id,
        ])->assertCreated()->json();

        $this->withToken($this->token)->patchJson("/api/v1/clients/{$client['id']}", [
            ...$client,
            'client_name' => 'Updated Client',
            'status' => 'won',
            'project_id' => 999,
            'created_by' => 999,
            'last_activity_at' => now()->addYear()->toISOString(),
        ])->assertOk()
            ->assertJsonPath('client_name', 'Updated Client')
            ->assertJsonPath('status', 'new')
            ->assertJsonPath('project_id', Project::resolveDefault()->id)
            ->assertJsonPath('created_by', $this->admin->id);
    }

    public function test_agency_updates_are_reflected_in_old_visits_and_related_agencies_cannot_be_deleted(): void
    {
        $salesperson = Profile::query()->create([
            'full_name' => 'Default Rep',
            'is_salesperson' => true,
            'is_active' => true,
            'is_default' => true,
        ]);
        $agency = $this->createAgency();

        $visit = $this->withToken($this->token)->postJson('/api/v1/project-visits', [
            'agency_id' => $agency['id'],
            'visit_date' => now()->toISOString(),
            'sales_rep_id' => $salesperson->id,
        ])->assertCreated();

        $this->withToken($this->token)->patchJson("/api/v1/agencies/{$agency['id']}", [
            'contact_person' => 'Updated Contact',
            'phone' => '+90 222',
            'email' => 'new@example.com',
        ])->assertOk()->assertJsonPath('email', 'new@example.com');

        $this->withToken($this->token)->getJson('/api/v1/project-visits')
            ->assertOk()
            ->assertJsonPath('data.0.id', $visit->json('id'))
            ->assertJsonPath('data.0.contact_person', 'Updated Contact')
            ->assertJsonPath('data.0.phone', '+90 222');

        $this->withToken($this->token)->deleteJson("/api/v1/agencies/{$agency['id']}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'AGENCY_HAS_RELATED_RECORDS');
    }

    private function createAgency(): array
    {
        return $this->withToken($this->token)->postJson('/api/v1/agencies', [
            'name' => 'North Star Agency',
            'category' => 'small_agency',
            'contact_person' => 'Agency Contact',
            'phone' => '+90 111',
            'email' => 'agency@example.com',
            'address' => 'Istanbul',
        ])->assertCreated()->json();
    }
}
