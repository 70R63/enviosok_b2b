<?php

namespace App\Services;

use App\Models\B2cIncidencia;
use App\Models\B2cIncidenciaEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

class IncidentWorkflowService
{
    private const TRANSITIONS = [
        'ABIERTA'=>['EN_REVISION','ASIGNADA'],
        'EN_REVISION'=>['ASIGNADA','EN_PROCESO'],
        'ASIGNADA'=>['EN_PROCESO'],
        'EN_PROCESO'=>['RESUELTA'],
        'RESUELTA'=>['EN_PROCESO','CERRADA'],
        'CERRADA'=>[],
    ];

    public function assign(B2cIncidencia $incident, int $assignee, string $priority, int $actor, string $origin='CRM'): B2cIncidencia
    {
        return DB::transaction(function() use($incident,$assignee,$priority,$actor,$origin){
            $incident=B2cIncidencia::lockForUpdate()->findOrFail($incident->id);
            if($incident->estatus==='CERRADA') throw new DomainException('Una incidencia cerrada no puede reasignarse.');
            $oldStatus=$incident->estatus;$oldAssignee=$incident->assigned_to;
            $incident->update(['assigned_to'=>$assignee,'assigned_at'=>now(),'prioridad'=>$priority,'estatus'=>'ASIGNADA']);
            $this->event($incident,$actor,$origin,'ASSIGNMENT',$oldStatus,'ASIGNADA',$oldAssignee,$assignee,$priority);
            return $incident;
        });
    }

    public function status(B2cIncidencia $incident,string $status,int $actor,string $origin): B2cIncidencia
    {
        $old=$incident->estatus;
        if(!in_array($status,self::TRANSITIONS[$old]??[],true)) throw new DomainException("Transición inválida de {$old} a {$status}.");
        $values=['estatus'=>$status];
        if($status==='RESUELTA')$values['resolved_at']=now();
        if($status==='CERRADA')$values['closed_at']=now();
        $incident->update($values);
        $this->event($incident,$actor,$origin,'STATUS',$old,$status,$incident->assigned_to,$incident->assigned_to,$incident->prioridad);
        return $incident;
    }

    public function message(B2cIncidencia $incident,int $actor,string $origin,?string $public,?string $internal,bool $solution=false): B2cIncidencia
    {
        return DB::transaction(function() use($incident,$actor,$origin,$public,$internal,$solution){
            $old=$incident->estatus;$new=$old;
            if($solution&&$old!=='RESUELTA'){$this->status($incident,'RESUELTA',$actor,$origin);$new='RESUELTA';}
            $incident->update(array_filter([
                'public_response'=>$public,'respuesta_admin'=>$public,'respondida_at'=>$public?now():null,
                'respondida_por'=>$public?$actor:null,'internal_notes'=>$internal,
            ],fn($v)=>$v!==null));
            $this->event($incident,$actor,$origin,$internal?'INTERNAL_NOTE':'RESPONSE',$old,$new,$incident->assigned_to,$incident->assigned_to,$incident->prioridad,$public,$internal);
            return $incident;
        });
    }

    public function customerComment(B2cIncidencia $incident,int $actor,string $message): void
    {
        if($incident->estatus==='CERRADA')throw new DomainException('La incidencia está cerrada.');
        $this->event($incident,$actor,'B2C','CUSTOMER_COMMENT',$incident->estatus,$incident->estatus,$incident->assigned_to,$incident->assigned_to,$incident->prioridad,$message);
    }

    private function event(B2cIncidencia $i,int $user,string $origin,string $type,?string $old,?string $new,$oldTo,$newTo,?string $priority,?string $public=null,?string $internal=null): void
    {
        B2cIncidenciaEvent::create(['incidencia_id'=>$i->id,'user_id'=>$user,'origin'=>$origin,'event_type'=>$type,'previous_status'=>$old,'new_status'=>$new,'previous_assigned_to'=>$oldTo,'new_assigned_to'=>$newTo,'priority'=>$priority,'public_message'=>$public,'internal_note'=>$internal]);
    }
}
