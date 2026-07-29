<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'b2c_identity_verifications',
            function (Blueprint $table) {
                $table->string('document_disk', 30)
                    ->nullable()
                    ->after('selfie_with_ine');

                $table->timestamp('submitted_at')
                    ->nullable()
                    ->after('status');

                $table->json('correction_documents')
                    ->nullable()
                    ->after('comments');

                $table->index(
                    ['status', 'submitted_at'],
                    'b2c_identity_status_submitted_idx'
                );
            }
        );

        DB::table('b2c_identity_verifications')
            ->where('status', 'EN_REVISION')
            ->update([
                'status' => 'PENDIENTE',
            ]);

        DB::table('b2c_identity_verifications')
            ->whereNull('document_disk')
            ->where(function ($query) {
                $query
                    ->whereNotNull('ine_front')
                    ->orWhereNotNull('ine_back')
                    ->orWhereNotNull('selfie_with_ine');
            })
            ->update([
                'document_disk' => 'public',
            ]);

        Schema::create(
            'b2c_identity_verification_events',
            function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger(
                    'identity_verification_id'
                );

                $table->foreign(
                    'identity_verification_id',
                    'b2c_idv_event_identity_fk'
                )
                    ->references('id')
                    ->on('b2c_identity_verifications')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('user_id');

                $table->foreign(
                    'user_id',
                    'b2c_idv_event_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->string('event_type', 50);
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->text('comments')->nullable();
                $table->json('metadata')->nullable();

                $table->unsignedBigInteger(
                    'performed_by'
                )->nullable();

                $table->foreign(
                    'performed_by',
                    'b2c_idv_event_actor_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(
                    ['identity_verification_id', 'created_at'],
                    'b2c_identity_event_created_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'b2c_identity_verification_events'
        );

        Schema::table(
            'b2c_identity_verifications',
            function (Blueprint $table) {
                $table->dropIndex(
                    'b2c_identity_status_submitted_idx'
                );

                $table->dropColumn([
                    'document_disk',
                    'submitted_at',
                    'correction_documents',
                ]);
            }
        );
    }
};