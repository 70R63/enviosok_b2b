<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLeadTrackingFieldsToCrmClientsTable extends Migration
{
    public function up()
    {
        Schema::table('crm_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_clients', 'lead_status')) {
                $table->string('lead_status')->default('nuevo')->after('commercial_status');
            }

            if (!Schema::hasColumn('crm_clients', 'lead_priority')) {
                $table->string('lead_priority')->default('media')->after('lead_status');
            }

            if (!Schema::hasColumn('crm_clients', 'last_contact_at')) {
                $table->timestamp('last_contact_at')->nullable()->after('lead_priority');
            }

            if (!Schema::hasColumn('crm_clients', 'next_follow_up_at')) {
                $table->timestamp('next_follow_up_at')->nullable()->after('last_contact_at');
            }

            if (!Schema::hasColumn('crm_clients', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('crm_clients', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('internal_notes');
            }
        });
    }

    public function down()
    {
        Schema::table('crm_clients', function (Blueprint $table) {
            $columns = [
                'lead_status',
                'lead_priority',
                'last_contact_at',
                'next_follow_up_at',
                'internal_notes',
                'reviewed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('crm_clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}