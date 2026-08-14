<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('customer_profile_id')->nullable()->after('requester_user_id')->constrained('tenant_customer_profiles')->nullOnDelete();
            $table->index(['tenant_id', 'customer_profile_id', 'status'], 'sup_ticket_customer_status_ix');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['customer_profile_id']);
            $table->dropIndex('sup_ticket_customer_status_ix');
            $table->dropColumn('customer_profile_id');
        });
    }
};
