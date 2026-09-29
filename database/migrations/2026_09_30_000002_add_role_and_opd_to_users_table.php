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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('remember_token')->constrained()->nullOnDelete();
            $table->foreignId('opd_id')->nullable()->after('role_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('opd_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['opd_id']);
            $table->dropColumn(['role_id', 'opd_id', 'is_active']);
        });
    }
};
