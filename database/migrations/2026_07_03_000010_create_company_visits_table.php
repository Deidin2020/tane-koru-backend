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
        Schema::create('company_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('agency_id')->constrained('agencies_companies')->restrictOnDelete();
            $table->dateTime('visit_date');
            $table->enum('category', ['large_company', 'medium_company', 'small_agency', 'individual_agent'])->nullable();
            $table->string('contact_person')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('sales_rep_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('visit_date');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_visits');
    }
};
