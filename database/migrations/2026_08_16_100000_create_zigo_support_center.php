<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique('sup_tickets_uuid_uq');
            $table->foreignId('tenant_id')->nullable(); $table->foreignId('requester_user_id');
            $table->string('requester_type', 24); $table->string('scope', 24); $table->string('channel', 24)->default('WEB');
            $table->string('category', 40); $table->string('priority', 16)->default('NORMAL'); $table->string('status', 24)->default('OPEN');
            $table->string('subject', 180); $table->text('description');
            $table->foreignId('assigned_user_id')->nullable(); $table->string('assigned_team', 80)->nullable();
            $table->foreignId('related_operation_id')->nullable(); $table->foreignId('related_shipment_id')->nullable();
            $table->foreignId('related_checkout_id')->nullable(); $table->foreignId('related_driver_profile_id')->nullable();
            $table->string('public_reference', 20)->unique('sup_tickets_public_ref_uq');
            $table->timestamp('first_response_at')->nullable(); $table->timestamp('resolved_at')->nullable(); $table->timestamp('closed_at')->nullable(); $table->timestamps();
            $table->foreign('tenant_id', 'sup_ticket_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('requester_user_id', 'sup_ticket_requester_fk')->references('id')->on('users');
            $table->foreign('assigned_user_id', 'sup_ticket_assignee_fk')->references('id')->on('users');
            $table->foreign('related_operation_id', 'sup_ticket_operation_fk')->references('id')->on('network_tenant_operations');
            $table->foreign('related_shipment_id', 'sup_ticket_shipment_fk')->references('id')->on('local_shipments');
            $table->foreign('related_checkout_id', 'sup_ticket_checkout_fk')->references('id')->on('tenant_customer_checkouts');
            $table->foreign('related_driver_profile_id', 'sup_ticket_driver_fk')->references('id')->on('tenant_driver_profiles');
            $table->index(['tenant_id','scope','status'], 'sup_ticket_tenant_scope_status_ix');
            $table->index(['requester_user_id','status'], 'sup_ticket_requester_status_ix');
            $table->index(['assigned_user_id','status'], 'sup_ticket_assignee_status_ix');
        });
        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id(); $table->foreignId('ticket_id'); $table->foreignId('author_user_id');
            $table->string('visibility', 16)->default('PUBLIC'); $table->text('message'); $table->timestamps();
            $table->foreign('ticket_id', 'sup_msg_ticket_fk')->references('id')->on('support_tickets');
            $table->foreign('author_user_id', 'sup_msg_author_fk')->references('id')->on('users');
            $table->index(['ticket_id','created_at'], 'sup_msg_ticket_created_ix');
        });
        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id(); $table->foreignId('ticket_id'); $table->foreignId('message_id')->nullable(); $table->foreignId('uploaded_by_user_id');
            $table->string('original_name', 255); $table->string('stored_path', 500); $table->string('mime', 80); $table->unsignedBigInteger('size'); $table->timestamps();
            $table->foreign('ticket_id', 'sup_att_ticket_fk')->references('id')->on('support_tickets');
            $table->foreign('message_id', 'sup_att_message_fk')->references('id')->on('support_ticket_messages');
            $table->foreign('uploaded_by_user_id', 'sup_att_uploader_fk')->references('id')->on('users');
            $table->index(['ticket_id','created_at'], 'sup_att_ticket_created_ix');
        });
        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->id(); $table->foreignId('ticket_id'); $table->foreignId('actor_user_id')->nullable();
            $table->string('type', 32); $table->json('metadata')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->foreign('ticket_id', 'sup_event_ticket_fk')->references('id')->on('support_tickets');
            $table->foreign('actor_user_id', 'sup_event_actor_fk')->references('id')->on('users');
            $table->index(['ticket_id','created_at'], 'sup_event_ticket_created_ix');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_events'); Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages'); Schema::dropIfExists('support_tickets');
    }
};
