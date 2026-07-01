<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('severity')->index();   // low | medium | high | critical
            $table->string('category')->index();   // bidding | prebid | gam | web_vitals | revenue | network | slot
            $table->string('metric');
            $table->decimal('current_value', 14, 4)->nullable();
            $table->decimal('threshold_value', 14, 4)->nullable();
            $table->string('entity_type')->nullable();
            $table->uuid('entity_id')->nullable();
            $table->string('entity_label')->nullable();
            $table->foreignUuid('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->text('message');
            $table->text('suggested_action')->nullable();
            $table->string('status')->default('open')->index(); // open | acknowledged | resolved
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('triggered_at')->index();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });

        Schema::create('insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->string('type')->default('optimization'); // optimization | anomaly | trend
            $table->string('impact')->default('medium');      // low | medium | high
            $table->string('related_metric')->nullable();
            $table->foreignUuid('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->timestamp('generated_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
        Schema::dropIfExists('alerts');
    }
};
