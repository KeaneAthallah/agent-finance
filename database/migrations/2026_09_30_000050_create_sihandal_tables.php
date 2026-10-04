<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SIHANDAL integration (spec §17, §48, §49): match candidates are
     * suggestions until a human confirms them; syncs keep the full
     * request/response history so every write to the system of record
     * is replayable and auditable.
     */
    public function up(): void
    {
        Schema::create('sihandal_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Processing version the candidates were produced from.
            $table->unsignedInteger('version');

            // belanja | rekening | opd | program | kegiatan
            $table->string('entity_type')->index();

            // SIHANDAL resource id (string: accommodates UUIDs).
            $table->string('entity_id');

            // Raw resource payload as returned by SIHANDAL.
            $table->json('sihandal_data');

            // 0–100 confidence score from the matching service.
            $table->unsignedInteger('confidence');

            // pending | confirmed | rejected
            $table->string('status')->default('pending')->index();

            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'version']);
            $table->index(['document_id', 'status']);
        });

        Schema::create('sihandal_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sihandal_match_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('endpoint');
            $table->string('method')->default('POST');

            // Full request payload sent to SIHANDAL, and the response.
            $table->json('request');
            $table->json('response')->nullable();
            $table->unsignedInteger('http_status')->nullable();

            // pending | success | failed
            $table->string('status')->default('pending')->index();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamps();

            $table->index(['document_id', 'status']);
        });

        // When the document was last pushed to SIHANDAL (spec §5).
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
        Schema::dropIfExists('sihandal_syncs');
        Schema::dropIfExists('sihandal_matches');
    }
};
