<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('b2c_incidencias', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('prioridad')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->text('customer_message')->nullable()->after('descripcion');
            $table->text('public_response')->nullable()->after('respuesta_admin');
            $table->text('internal_notes')->nullable()->after('public_response');
            $table->timestamp('resolved_at')->nullable()->after('respondida_at');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
            $table->index(['estatus', 'prioridad', 'assigned_to'], 'b2c_incidencias_workflow_idx');
        });

        DB::table('b2c_incidencias')->whereNull('customer_message')
            ->update(['customer_message' => DB::raw('descripcion')]);
        DB::table('b2c_incidencias')->whereNull('public_response')->whereNotNull('respuesta_admin')
            ->update(['public_response' => DB::raw('respuesta_admin')]);

        Schema::create('b2c_incidencia_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('b2c_incidencias')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin', 20);
            $table->string('event_type', 30);
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->unsignedBigInteger('previous_assigned_to')->nullable();
            $table->unsignedBigInteger('new_assigned_to')->nullable();
            $table->string('priority')->nullable();
            $table->text('public_message')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();
            $table->index(['incidencia_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2c_incidencia_events');
        Schema::table('b2c_incidencias', function (Blueprint $table) {
            $table->dropIndex('b2c_incidencias_workflow_idx');
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['assigned_to','assigned_at','customer_message','public_response','internal_notes','resolved_at','closed_at']);
        });
    }
};
