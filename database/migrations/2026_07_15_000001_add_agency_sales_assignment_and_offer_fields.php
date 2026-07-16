<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable()->after('full_name');
            $table->boolean('is_salesperson')->default(false)->after('avatar');
            $table->boolean('is_active')->default(true)->after('is_salesperson');
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['is_salesperson', 'is_active']);
            $table->index('is_default');
        });

        Schema::table('agencies_companies', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('phone');
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->text('offer_details')->nullable()->after('objection');
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX profiles_one_default_salesperson ON profiles (is_default) WHERE is_default = 1 AND is_salesperson = 1 AND deleted_at IS NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE profiles ADD COLUMN default_salesperson_guard TINYINT GENERATED ALWAYS AS (IF(is_default = 1 AND is_salesperson = 1 AND deleted_at IS NULL, 1, NULL)) STORED');
            DB::statement('CREATE UNIQUE INDEX profiles_one_default_salesperson ON profiles (default_salesperson_guard)');
        }

        DB::table('profiles')
            ->whereNotNull('user_id')
            ->update([
                'email' => DB::raw('(SELECT email FROM users WHERE users.id = profiles.user_id)'),
            ]);

        DB::table('profiles')
            ->whereIn('user_id', function ($query): void {
                $query->select('user_roles.user_id')
                    ->from('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('roles.name', 'salesperson');
            })
            ->update(['is_salesperson' => true]);

        DB::table('agencies_companies')->orderBy('id')->each(function (object $agency): void {
            $projectVisit = DB::table('project_visits')
                ->where('agency_id', $agency->id)
                ->whereNull('deleted_at')
                ->latest('visit_date')
                ->first();
            $companyVisit = DB::table('company_visits')
                ->where('agency_id', $agency->id)
                ->whereNull('deleted_at')
                ->latest('visit_date')
                ->first();

            DB::table('agencies_companies')->where('id', $agency->id)->update([
                'contact_person' => $agency->contact_person ?: ($projectVisit?->contact_person ?: $companyVisit?->contact_person),
                'phone' => $agency->phone ?: $projectVisit?->phone,
                'address' => $agency->address ?: $companyVisit?->address,
            ]);
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS profiles_one_default_salesperson');
        } elseif ($driver === 'mysql') {
            DB::statement('DROP INDEX profiles_one_default_salesperson ON profiles');
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('default_salesperson_guard');
            });
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('offer_details');
        });

        Schema::table('agencies_companies', function (Blueprint $table): void {
            $table->dropColumn('email');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['is_salesperson', 'is_active']);
            $table->dropIndex(['is_default']);
            $table->dropColumn(['email', 'is_salesperson', 'is_active', 'is_default']);
        });

        DB::table('profiles')->whereNull('user_id')->delete();

        Schema::table('profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
