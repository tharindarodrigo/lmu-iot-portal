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
        Schema::create('virtual_device_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_device_id')
                ->constrained('devices')
                ->cascadeOnDelete();
            $table->foreignId('source_device_id')
                ->constrained('devices')
                ->cascadeOnDelete();
            $table->string('purpose', 100);
            $table->unsignedInteger('sequence')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['virtual_device_id', 'source_device_id', 'purpose'], 'virtual_device_links_unique_source_purpose');
            $table->index(['virtual_device_id', 'purpose'], 'virtual_device_links_virtual_purpose_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_device_links');
    }
};
