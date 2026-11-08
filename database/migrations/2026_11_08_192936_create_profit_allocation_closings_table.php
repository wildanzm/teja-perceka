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
        Schema::create('profit_allocation_closings', function (Blueprint $table) {
            $table->id();
            $table->string('period_mode', 20); // monthly, semester, yearly
            $table->string('period_key', 40)->index(); // e.g. '2026-10', 'semester-1-2026', 'yearly-2026'
            $table->date('closing_date');
            $table->decimal('net_income', 15, 2)->default(0);
            $table->decimal('total_outflow', 15, 2)->default(0);
            $table->decimal('total_retained', 15, 2)->default(0);
            $table->json('journal_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_allocation_closings');
    }
};
