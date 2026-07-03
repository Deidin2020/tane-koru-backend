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
        Schema::create('client_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->enum('activity_type', ['client_created', 'status_changed', 'follow_up', 'note', 'document_uploaded', 'reservation_uploaded']);
            $table->enum('from_status', ['new', 'presentation_completed', 'follow_up', 'reservation', 'deal', 'not_interested'])->nullable();
            $table->enum('to_status', ['new', 'presentation_completed', 'follow_up', 'reservation', 'deal', 'not_interested'])->nullable();
            $table->text('message')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index('activity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_activities');
    }
};
