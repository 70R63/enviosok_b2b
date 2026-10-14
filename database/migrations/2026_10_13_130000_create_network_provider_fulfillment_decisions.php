<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_provider_fulfillment_decisions', function (Blueprint $t): void {
            $t->engine = 'InnoDB';
            $t->id();
            $t->uuid('uuid')->unique('net_pfd_uuid_uq');
            $t->foreignId('provider_fulfillment_id')->unique('net_pfd_fulfillment_uq');
            $t->string('status', 24);
            $t->foreignId('actor_user_id');
            $t->string('channel', 24);
            $t->string('rejection_reason', 500)->nullable();
            $t->dateTime('decided_at');
            $t->timestamps();
            $t->foreign('provider_fulfillment_id', 'net_pfd_fulfillment_fk')->references('id')->on('network_provider_fulfillments');
            $t->foreign('actor_user_id', 'net_pfd_actor_fk')->references('id')->on('users');
            $t->index(['status', 'decided_at'], 'net_pfd_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_provider_fulfillment_decisions');
    }
};
