<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Synthetic uptime / response-time monitoring checks per domain.
 * Dashboards aggregate uptime % and response latency from this event log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->timestamp('checked_at')->index();
            $table->string('status')->index(); // up | down
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->unsignedInteger('http_status')->default(200);
            $table->string('region')->nullable(); // monitoring region
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->index(['domain_id', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_checks');
    }
};
