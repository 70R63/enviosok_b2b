<?php
namespace App\Mail;use Illuminate\Bus\Queueable;use Illuminate\Mail\Mailable;use Illuminate\Queue\SerializesModels;
final class CrmAdminEventMail extends Mailable{use Queueable,SerializesModels;public function __construct(public string $eventType,public array $details,public string $crmUrl){}public function build(){return $this->subject(match($this->eventType){'invoice_request'=>'Nueva solicitud de facturación ZIGO','incident'=>'Nueva incidencia ZIGO',default=>'Nuevo prospecto ZIGO'})->view('email.crm-admin-event');}}
