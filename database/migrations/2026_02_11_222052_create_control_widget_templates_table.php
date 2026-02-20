<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_widget_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('widget_type')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('input_component');
            $table->boolean('supports_realtime')->default(true);
            $table->json('schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_widget_templates');
    }
};
