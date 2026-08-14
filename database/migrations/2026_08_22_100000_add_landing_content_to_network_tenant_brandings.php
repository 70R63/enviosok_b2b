<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('network_tenant_brandings', function (Blueprint $table): void {
            $table->string('tagline', 120)->nullable()->after('brand_name');
            $table->json('landing_cards')->nullable()->after('support_phone');
        });
    }

    public function down(): void
    {
        Schema::table('network_tenant_brandings', fn (Blueprint $table) => $table->dropColumn(['tagline', 'landing_cards']));
    }
};
