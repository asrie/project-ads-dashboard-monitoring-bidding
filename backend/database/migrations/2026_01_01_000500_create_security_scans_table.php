<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security Site Inspector scan history (one row per scan run, per domain).
 * The full structured result (DNS, SSL, headers, WHOIS, ports, ...) is stored
 * as JSON so the history page can re-render and export it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('target_url');
            $table->string('target_host');
            $table->string('status')->default('completed'); // completed | failed
            $table->string('grade')->nullable();            // A..F
            $table->unsignedSmallInteger('score')->nullable(); // 0..100
            $table->json('results')->nullable();             // full structured findings
            $table->text('error')->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['domain_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_scans');
    }
};
