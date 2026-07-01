<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimension / reference tables: domains, pages, ad slots, bidders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('path');
            $table->string('title')->nullable();
            $table->timestamps();
            $table->index(['domain_id', 'path']);
        });

        Schema::create('ad_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('name');
            $table->string('ad_unit_path');
            $table->json('sizes')->nullable();
            $table->string('device')->default('desktop')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('domain_id');
        });

        Schema::create('bidders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidders');
        Schema::dropIfExists('ad_slots');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('domains');
    }
};
