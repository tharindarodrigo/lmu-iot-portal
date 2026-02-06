<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 100)->index();
            $table->string('name');
            $table->string('default_protocol', 50);
            $table->jsonb('protocol_config');
            $table->timestamps();

            // Org-specific types: key must be unique per organization
            $table->unique(['organization_id', 'key'], 'device_types_org_key_unique');
        });

        // Global types: key must be unique when organization_id is null
        DB::statement('CREATE UNIQUE INDEX device_types_global_key_unique ON device_types (key) WHERE organization_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS device_types_global_key_unique');
        Schema::dropIfExists('device_types');
    }
};
