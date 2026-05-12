<?php

declare(strict_types=1);

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
        if (Schema::hasTable('coin_alert_settings')) {
            return;
        }

        Schema::create('coin_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('mode', ['auto', 'manual'])
                ->default('auto')
                ->comment('Alert mode: auto (system decides) or manual (use custom threshold)');
            $table->enum('type', ['preset', 'custom'])
                ->default('preset')
                ->comment('preset: from predefined list, custom: user-defined percent');
            $table->decimal('threshold_percent', 5, 2)
                ->nullable()
                ->comment('Custom threshold percent for manual mode');
            $table->boolean('is_active')
                ->default(true)
                ->comment('Enable or disable this alert setting');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('coin_alert_settings')) {
            return;
        }

        Schema::dropIfExists('coin_alert_settings');
    }
};
