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
        Schema::create('derived_parameter_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_schema_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label', 255);
            $table->string('data_type', 50);
            $table->string('unit', 50)->nullable();
            $table->jsonb('expression');
            $table->jsonb('dependencies')->nullable();
            $table->string('json_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['device_schema_version_id', 'key'], 'derived_param_defs_version_key_unique');
            $table->index(['device_schema_version_id', 'data_type'], 'derived_param_defs_version_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('derived_parameter_definitions');
    }
};
