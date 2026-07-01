<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad-layout preview captures: a mobile screenshot of a publisher page plus the
 * geometry of detected ad slots (GPT/Prebid), rendered server-side by Playwright.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('device')->default('mobile');
            $table->string('url');
            $table->string('status')->default('completed'); // completed | failed
            $table->string('image_path')->nullable();
            $table->unsignedInteger('page_width')->default(0);   // CSS px
            $table->unsignedInteger('page_height')->default(0);  // CSS px
            $table->unsignedInteger('viewport_css_width')->default(390);
            $table->unsignedSmallInteger('slot_count')->default(0);
            $table->json('slots')->nullable();   // enriched slot map
            $table->json('header')->nullable();  // header/nav rect
            $table->text('error')->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->index(['domain_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_previews');
    }
};
