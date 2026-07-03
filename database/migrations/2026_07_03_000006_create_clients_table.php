<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies_companies')->nullOnDelete();
            $table->string('client_name');
            $table->string('phone', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->enum('lead_source', ['agency', 'direct', 'referral']);
            $table->string('direct_source')->nullable();
            $table->string('referral_name')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('required_unit')->nullable();
            $table->enum('payment_method', ['cash', 'installments'])->nullable();
            $table->enum('purchase_purpose', ['citizenship', 'investment', 'residence'])->nullable();
            $table->date('visit_date')->nullable();
            $table->foreignId('assigned_salesperson_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->boolean('presentation_completed')->default(false);
            $table->text('objection')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['new', 'presentation_completed', 'follow_up', 'reservation', 'deal', 'not_interested'])->default('new');
            $table->text('not_interested_reason')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('follow_up_date');
            $table->index('created_at');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
