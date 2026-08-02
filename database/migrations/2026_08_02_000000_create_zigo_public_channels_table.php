<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_public_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('channel')->unique();
            $table->string('label')->nullable();
            $table->string('value')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_public_channels');
    }
};
