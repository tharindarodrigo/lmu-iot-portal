<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 32);
            $table->boolean('enabled')->default(true);
            $table->jsonb('recipients');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('mask')->nullable();
            $table->string('campaign_name')->nullable();
            $table->jsonb('legacy_metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'channel']);
            $table->index(['organization_id', 'enabled']);
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_profiles');
    }
};
