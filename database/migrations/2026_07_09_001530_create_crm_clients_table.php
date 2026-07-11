<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_clients', function (Blueprint $table) {
            $table->id();

            $table->string('client_type', 30)->default('b2c');
            $table->string('commercial_status', 30)->default('prospecto');

            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('contact_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_id')->nullable();

            $table->string('source')->default('manual');
            $table->text('notes')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('client_type');
            $table->index('commercial_status');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_clients');
    }
};