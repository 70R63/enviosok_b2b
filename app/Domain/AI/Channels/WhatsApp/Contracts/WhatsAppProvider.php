<?php
namespace App\Domain\AI\Channels\WhatsApp\Contracts;use App\Domain\AI\Channels\WhatsApp\Data\{WhatsAppOutboundConfirmationRequest,WhatsAppOutboundTextRequest};
interface WhatsAppProvider{public function sendText(WhatsAppOutboundTextRequest$request):string;public function sendConfirmation(WhatsAppOutboundConfirmationRequest$request):string;}
