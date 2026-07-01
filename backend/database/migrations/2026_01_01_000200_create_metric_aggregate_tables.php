<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily metric tables. Dashboards read from these, never from
 * raw events (CLAUDE.md Database Guidelines §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_performance_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('slot_id')->constrained('ad_slots')->cascadeOnDelete();
            $table->string('device')->index();
            $table->unsignedBigInteger('ad_requests')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->decimal('revenue', 14, 4)->default(0);
            $table->decimal('ecpm', 10, 4)->default(0);
            $table->decimal('fill_rate', 6, 3)->default(0);
            $table->decimal('viewability', 6, 3)->default(0);
            $table->timestamps();
            $table->index(['domain_id', 'date']);
            $table->index(['slot_id', 'date']);
        });

        Schema::create('bidder_performance_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('bidder_id')->constrained('bidders')->cascadeOnDelete();
            $table->unsignedBigInteger('bid_requests')->default(0);
            $table->unsignedBigInteger('bid_responses')->default(0);
            $table->unsignedBigInteger('bids_won')->default(0);
            $table->unsignedBigInteger('timeouts')->default(0);
            $table->unsignedBigInteger('errors')->default(0);
            $table->decimal('avg_latency_ms', 10, 2)->default(0);
            $table->decimal('revenue', 14, 4)->default(0);
            $table->decimal('avg_cpm', 10, 4)->default(0);
            $table->timestamps();
            $table->index(['domain_id', 'date']);
            $table->index(['bidder_id', 'date']);
        });

        Schema::create('prebid_auctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('auction_id')->index();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('device')->index();
            $table->timestamp('started_at')->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('bidder_count')->default(0);
            $table->unsignedInteger('bids_received')->default(0);
            $table->unsignedInteger('timeouts')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->string('won_bidder')->nullable();
            $table->decimal('cpm', 10, 4)->default(0);
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->index(['domain_id', 'started_at']);
        });

        Schema::create('gam_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('device')->index();
            $table->timestamp('requested_at')->index();
            $table->string('ad_unit');
            $table->string('status')->index(); // success | empty | failed
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('http_status')->default(200);
            $table->string('line_item_id')->nullable();
            $table->string('creative_id')->nullable();
            $table->timestamps();
            $table->index(['domain_id', 'requested_at']);
        });

        Schema::create('network_ad_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('device')->index();
            $table->timestamp('observed_at')->index();
            $table->text('resource_url');
            $table->string('vendor')->nullable()->index();
            $table->string('type')->default('script'); // script | xhr | img | css | font
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('is_third_party')->default(true);
            $table->boolean('is_blocking')->default(false);
            $table->unsignedInteger('status_code')->default(200);
            $table->timestamps();
            $table->index(['domain_id', 'observed_at']);
        });

        Schema::create('web_vitals_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('device')->index();
            $table->decimal('lcp', 8, 1)->default(0);   // ms
            $table->decimal('inp', 8, 1)->default(0);   // ms
            $table->decimal('cls', 6, 3)->default(0);   // unitless
            $table->decimal('fcp', 8, 1)->default(0);   // ms
            $table->decimal('ttfb', 8, 1)->default(0);  // ms
            $table->decimal('tbt', 8, 1)->default(0);   // ms
            $table->unsignedBigInteger('samples')->default(0);
            $table->timestamps();
            $table->index(['domain_id', 'date']);
            $table->index(['page_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_vitals_daily');
        Schema::dropIfExists('network_ad_requests');
        Schema::dropIfExists('gam_requests');
        Schema::dropIfExists('prebid_auctions');
        Schema::dropIfExists('bidder_performance_daily');
        Schema::dropIfExists('slot_performance_daily');
    }
};
