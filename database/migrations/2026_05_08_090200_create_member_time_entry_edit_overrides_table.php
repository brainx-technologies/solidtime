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
        Schema::create('member_time_entry_edit_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            /** Calendar date (policy timezone) for which locked own entries may be edited while the override is active */
            $table->date('applies_on');
            $table->timestamp('editable_until');
            $table->timestamps();

            $table->unique(['organization_id', 'member_id', 'applies_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_time_entry_edit_overrides');
    }
};
