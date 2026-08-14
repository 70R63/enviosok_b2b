<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_tenant_brandings', function (Blueprint $table): void {
            $table->string('hero_image_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('network_tenant_brandings', function (Blueprint $table): void {
            $table->dropColumn('hero_image_path');
        });
    }
};
