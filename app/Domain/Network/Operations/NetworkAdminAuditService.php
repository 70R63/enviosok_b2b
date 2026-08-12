<?php

namespace App\Domain\Network\Operations;

use App\Domain\Network\Operations\Models\NetworkAdminAuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class NetworkAdminAuditService
{
    private const SENSITIVE = ['password','password_hash','token','access_token','secret','app_key','init_point'];

    public function record(int $actorId, string $action, Model $entity, array $before = [], array $after = []): void
    {
        if (!Schema::hasTable('network_admin_audit_events')) {
            return;
        }
        NetworkAdminAuditEvent::create(['actor_user_id'=>$actorId,'action'=>$action,'entity_type'=>$entity::class,'entity_id'=>$entity->getKey(),'before_json'=>$this->clean($before),'after_json'=>$this->clean($after),'created_at'=>now()]);
    }

    private function clean(array $data): array
    {
        return collect($data)->reject(fn ($value, $key) => collect(self::SENSITIVE)->contains(fn ($word) => str_contains(strtolower((string) $key), $word)))->all();
    }
}
