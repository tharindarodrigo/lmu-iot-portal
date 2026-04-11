<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_workflows', function (Blueprint $table): void {
            $table->boolean('is_managed')->default(false)->after('status');
            $table->string('managed_type', 64)->nullable()->after('is_managed');
            $table->jsonb('managed_metadata')->nullable()->after('managed_type');

            $table->index(['organization_id', 'is_managed']);
            $table->index(['is_managed', 'managed_type']);
        });
    }

    public function down(): void
    {
        Schema::table('automation_workflows', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'is_managed']);
            $table->dropIndex(['is_managed', 'managed_type']);
            $table->dropColumn(['is_managed', 'managed_type', 'managed_metadata']);
        });
    }
};
