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
        Schema::create('schema_version_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_schema_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label', 255);
            $table->string('direction', 50);
            $table->string('suffix', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('qos')->default(1);
            $table->boolean('retain')->default(false);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['device_schema_version_id', 'key'], 'schema_version_topics_version_key_unique');
            $table->unique(['device_schema_version_id', 'suffix'], 'schema_version_topics_version_suffix_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_version_topics');
    }
};
