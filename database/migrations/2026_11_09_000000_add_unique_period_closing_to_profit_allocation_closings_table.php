<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One profit-allocation period may be closed at most once.
 *
 * applyAllocation() guarded re-closing with a plain, non-locking SELECT
 * (currentClosing) evaluated before any transaction, so two concurrent sessions
 * could both pass the guard and each commit a closing plus a full set of KBM
 * journals. A unique key makes the database the arbiter instead of the check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_allocation_closings', function (Blueprint $table) {
            $table->unique(['period_mode', 'period_key'], 'profit_allocation_closings_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('profit_allocation_closings', function (Blueprint $table) {
            $table->dropUnique('profit_allocation_closings_period_unique');
        });
    }
};
