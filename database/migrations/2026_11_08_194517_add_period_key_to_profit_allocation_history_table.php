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
        Schema::table('profit_allocation_history', function (Blueprint $table) {
            $table->string('period_mode', 20)->nullable()->after('effective_from');
            $table->string('period_key', 40)->nullable()->index()->after('period_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_allocation_history', function (Blueprint $table) {
            $table->dropIndex(['period_key']);
            $table->dropColumn(['period_mode', 'period_key']);
        });
    }
};
