<?php
namespace App\Domain\AI\Launchpad\Advisors;
use App\Domain\AI\Agents\Enums\AgentType;
use App\Domain\AI\Launchpad\Contracts\AgentLaunchpadAdvisor;
use App\Domain\AI\Launchpad\Data\{LaunchpadIntakeData,LaunchpadRecommendationData};
final class RuleBasedAgentLaunchpadAdvisor implements AgentLaunchpadAdvisor
{
    public function recommend(LaunchpadIntakeData $i):LaunchpadRecommendationData
    {
        [$code,$confidence,$alternatives,$missing]=$this->resolve($i);
        $maps=[
            'sales'=>[AgentType::Sales,'Asesor de ventas','Capturar y calificar oportunidades para facilitar el seguimiento comercial.',['lead_captured','lead_qualified'],['captura de datos de contacto','calificación de oportunidades','seguimiento comercial propuesto']],
            'customer_service'=>[AgentType::CustomerService,'Asistente de atención','Resolver preguntas frecuentes y recopilar contexto antes de escalar.',['conversation_resolved','human_handoff'],['respuesta de preguntas frecuentes','recopilación de contexto','resolución básica']],
            'booking'=>[AgentType::Booking,'Asistente de citas','Recopilar disponibilidad y registrar solicitudes de cita para confirmación.',['appointment_requested'],['recopilación de disponibilidad','solicitud de cita','confirmación humana sin integración']],
            'quote'=>[AgentType::Quote,'Asistente de cotizaciones','Recopilar la información necesaria para solicitar una cotización no vinculante.',['quote_requested'],['recopilación de requerimientos','solicitud de cotización','escalamiento para precio confirmado']],
            'custom_operational'=>[AgentType::CustomOperational,'Asistente operativo','Recopilar información estructurada y proponer una solicitud operativa para confirmación.',['operational_request_created'],['recopilación estructurada','acción operativa propuesta','confirmación humana']],
        ];
        [$type,$defaultName,$job,$outcomes,$caps]=$maps[$code];
        $name=$i->desiredAgentName?:$defaultName;
        $channels=array_values(array_unique(array_merge(['webchat'],$i->requestedChannels)));
        $channelDetails=array_map(fn($c)=>['code'=>$c,'status'=>$c==='webchat'?'available':'pending'],$channels);
        $prohibited=['capturar pagos','modificar datos críticos sin confirmación','dar asesoría legal, médica o financiera','inventar precios','prometer integraciones no disponibles','publicar automáticamente'];
        if($code==='quote')$prohibited[]='generar cotizaciones vinculantes sin herramienta o integración';
        $knowledge=$i->knowledgeSourceTypes?:['manual'];
        $handoff=$this->handoffPolicy($i->handoffPreference??'unknown_or_low_confidence');
        $configuration=['identity'=>['name'=>$name,'type'=>$type->value,'language'=>$i->preferredLanguage],'goals'=>['job_to_be_done'=>$job,'outcomes'=>$outcomes],'behavior'=>['tone'=>'claro y profesional','collect_context'=>true],'guardrails'=>['prohibited'=>$prohibited],'qualification'=>['required_fields'=>['name','contact','request_context']],'handoff'=>$handoff,'capabilities'=>['allowed'=>$caps,'channels'=>$channelDetails],'metadata'=>['source'=>'launchpad','objective'=>$code]];
        return new LaunchpadRecommendationData($type,$name,$job,$outcomes,$caps,$prohibited,$channels,$knowledge,['recopilar información','responder con conocimiento aprobado','crear una solicitud para revisión humana'],$handoff,['expected'=>$outcomes],['mode'=>'managed','limits'=>'Se definirán durante la revisión de implementación.'],['response'=>'Objetivos sujetos a disponibilidad y revisión operativa.'],['data_minimization'=>true,'sensitive_data'=>'No solicitar secretos ni credenciales.'],['mode'=>'descriptive','policy'=>'Precios y cobros requieren una fuente o herramienta aprobada.'],$configuration,"La propuesta prioriza {$job}",['Chat web será el canal inicial.','Las políticas finales se validarán con ZIGO.'],$missing,($code==='quote'?['Los precios requieren validación humana o integración.']:[]),$alternatives,$confidence,'rules_v1','1.0');
    }
    private function resolve(LaunchpadIntakeData $i):array
    {
        if($i->objectiveCode!=='unsure')return[$i->objectiveCode,'high',[],[]];
        $text=mb_strtolower($i->problemDescription.' '.($i->desiredOutcomes??''));
        $keywords=['sales'=>['venta','ventas','lead','prospect','sell','sales'],'customer_service'=>['atención','soporte','cliente','faq','service','support'],'booking'=>['cita','agenda','reserv','appointment','booking'],'quote'=>['cotiz','precio','presupuesto','quote','pricing']];
        $scores=[];foreach($keywords as$code=>$words){$scores[$code]=0;foreach($words as$word)if(str_contains($text,$word))$scores[$code]++;}
        arsort($scores);$best=array_key_first($scores);$top=$scores[$best];$ties=array_keys(array_filter($scores,fn($v)=>$v===$top));
        if($top===0)return['custom_operational','low',['sales','customer_service'],['¿Cuál es el resultado principal que esperas?','¿Qué información debe recopilar el agente?']];
        $ambiguous=count($ties)>1;$alternatives=array_values(array_filter($ties,fn($v)=>$v!==$best));
        return[$best,$ambiguous?'low':($top>1?'high':'medium'),$alternatives,$ambiguous?['¿Qué objetivo debe tener prioridad?']:[]];
    }
    private function handoffPolicy(string $code):array
    {
        return match($code){
            'when_requested'=>['enabled'=>true,'code'=>$code,'trigger'=>'customer_request','action'=>'transfer_to_human'],
            'sensitive_actions'=>['enabled'=>true,'code'=>$code,'trigger'=>'sensitive_action','action'=>'require_human_confirmation'],
            'always_available'=>['enabled'=>true,'code'=>$code,'trigger'=>'customer_request','action'=>'offer_human_handoff'],
            default=>['enabled'=>true,'code'=>'unknown_or_low_confidence','triggers'=>['unknown_answer','low_confidence'],'action'=>'transfer_to_human'],
        };
    }
}
