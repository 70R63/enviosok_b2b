<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'api_billing_requests',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('api_client_id')
                    ->constrained('api_clients')
                    ->cascadeOnDelete();
                $table->foreignId('api_key_id')
                    ->nullable()
                    ->constrained('api_keys')
                    ->nullOnDelete();
                $table->string('environment', 20);
                $table->string('external_id', 120);
                $table->string('idempotency_key', 120);
                $table->char('payload_hash', 64);
                $table->string('source_system', 60);
                $table->string('status', 30)
                    ->default('SOLICITADA');
                $table->string('fulfillment_mode', 20)
                    ->default('MANUAL');
                $table->string('provider_code', 50)
                    ->nullable();
                $table->string('provider_reference', 150)
                    ->nullable();

                $table->string('payment_reference', 150);
                $table->string('payment_status', 30);
                $table->string('payment_method', 3);
                $table->string('payment_form', 2);
                $table->timestamp('payment_date')->nullable();
                $table->char('currency', 3)->default('MXN');
                $table->decimal('exchange_rate', 14, 6)
                    ->nullable();

                $table->decimal('subtotal', 14, 2);
                $table->decimal('discount_total', 14, 2)
                    ->default(0);
                $table->decimal('tax_total', 14, 2)
                    ->default(0);
                $table->decimal('shipping_total', 14, 2)
                    ->default(0);
                $table->decimal('insurance_total', 14, 2)
                    ->default(0);
                $table->decimal('total', 14, 2);

                $table->string('customer_rfc', 13);
                $table->string('customer_name', 254);
                $table->string('customer_postal_code', 5);
                $table->string('customer_tax_regime', 3);
                $table->string('customer_cfdi_use', 4);
                $table->string('customer_email', 254)
                    ->nullable();

                $table->json('request_payload');
                $table->json('response_payload')->nullable();
                $table->uuid('cfdi_uuid')->nullable()->unique();
                $table->string('pdf_path', 500)->nullable();
                $table->string('xml_path', 500)->nullable();
                $table->string('error_code', 80)->nullable();
                $table->text('error_message')->nullable();

                $table->timestamp('requested_at');
                $table->timestamp('processing_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'api_client_id',
                        'environment',
                        'external_id',
                    ],
                    'uq_api_bill_client_env_external'
                );
                $table->unique(
                    [
                        'api_client_id',
                        'environment',
                        'idempotency_key',
                    ],
                    'uq_api_bill_client_env_idempotency'
                );
                $table->index(
                    [
                        'api_client_id',
                        'environment',
                        'status',
                    ],
                    'idx_api_bill_client_env_status'
                );
            }
        );

        Schema::create(
            'api_billing_request_items',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('api_billing_request_id')
                    ->constrained('api_billing_requests')
                    ->cascadeOnDelete();
                $table->unsignedSmallInteger('line_number');
                $table->string('client_item_id', 120)
                    ->nullable();
                $table->string('category', 20);
                $table->string('product_service_code', 8);
                $table->string('unit_code', 3);
                $table->string('description', 255);
                $table->decimal('quantity', 14, 4);
                $table->decimal('unit_price', 14, 6);
                $table->decimal('discount', 14, 2)
                    ->default(0);
                $table->decimal('subtotal', 14, 2);
                $table->string('tax_object', 2);
                $table->decimal('tax_rate', 8, 6)
                    ->nullable();
                $table->decimal('tax_amount', 14, 2)
                    ->default(0);
                $table->decimal('total', 14, 2);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'api_billing_request_id',
                        'line_number',
                    ],
                    'uq_api_bill_item_request_line'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('api_billing_request_items');
        Schema::dropIfExists('api_billing_requests');
    }
};
