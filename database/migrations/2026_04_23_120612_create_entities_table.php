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
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('label')->nullable();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->timestamps();
            $table->index('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
