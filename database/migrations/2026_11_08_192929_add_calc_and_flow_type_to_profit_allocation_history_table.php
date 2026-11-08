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
            $table->string('calc_type', 20)->default('percentage')->after('percentage');
            $table->decimal('amount', 15, 2)->default(0)->after('calc_type');
            $table->string('flow_type', 20)->default('outflow')->after('allocation_group');
            $table->foreignId('account_id')->nullable()->after('flow_type')->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_allocation_history', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['calc_type', 'amount', 'flow_type', 'account_id']);
        });
    }
};
