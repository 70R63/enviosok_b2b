<?php

namespace App\Domain\AI\Leads\Services;

use App\Domain\AI\Agents\Models\AgentContractVersion;
use App\Domain\AI\Conversations\Enums\ConversationMessageRole;
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Leads\Data\LeadOutcomePolicy;
use App\Domain\AI\Leads\Enums\LeadStatus;
use App\Domain\AI\Leads\Enums\OutcomeStatus;
use App\Domain\AI\Leads\Enums\OutcomeType;
use App\Domain\AI\Leads\Models\Lead;
use App\Domain\AI\Leads\Models\OutcomeEvent;
use App\Domain\AI\Runtime\Enums\RuntimeRunStatus;
use App\Domain\AI\Runtime\Models\RuntimeRun;

final class RecordLeadOutcomeCandidatesService
{
    public function record(Conversation $conversation, ConversationMessage $message, RuntimeRun $run, ?array $candidate, bool $resolved): void
    {
        $contract = $this->assertAggregate($conversation, $message, $run);
        $policy = LeadOutcomePolicy::from($contract->outcome_policy ?? []);
        $lead = null;
        if ($candidate !== null) {
            $data = $this->normalize($candidate, $policy);
            $lead = Lead::query()->where('conversation_id', $conversation->id)->first();
            if (! $lead) {
                $lead = new Lead;
                $lead->agent_id = $conversation->agent_id;
                $lead->agent_version_id = $conversation->agent_version_id;
                $lead->agent_contract_version_id = $contract->id;
                $lead->conversation_id = $conversation->id;
                $lead->source_message_id = $message->id;
                $lead->runtime_run_id = $run->id;
                $lead->data = $data;
                $lead->detected_at = now();
                $lead->status = LeadStatus::Detected;
                $lead->save();
            } else {
                $this->assertLead($lead, $conversation, $contract);
                $effective = $lead->data;
                foreach ($data as $key => $value) {
                    if (! array_key_exists($key, $effective)) {
                        $effective[$key] = $value;
                    }
                }
                if ($effective !== $lead->data) {
                    $lead->enrich($effective, $lead->status);
                }
            }
            $effective = $lead->fresh()->data;
            $complete = $policy->requiredFields !== [] && array_diff($policy->requiredFields, array_keys($effective)) === [];
            $verified = $policy->permits(OutcomeType::ValidLead->value) && $complete;
            if ($verified && $lead->status !== LeadStatus::Verified) {
                $lead->enrich($effective, LeadStatus::Verified);
            }
            if ($policy->permits(OutcomeType::ValidLead->value)) {
                $this->outcome($conversation, $message, $run, $contract, $lead, OutcomeType::ValidLead, $verified ? OutcomeStatus::Verified : OutcomeStatus::Detected, ['required_fields_satisfied' => $complete, 'rule' => 'contract_required_fields_v1']);
            }
        }
        if ($resolved && $policy->permits(OutcomeType::ResolvedConsultation->value)) {
            $this->outcome($conversation, $message, $run, $contract, null, OutcomeType::ResolvedConsultation, OutcomeStatus::Detected, ['candidate_detected' => true, 'automatic_verification' => false]);
        }
    }

    private function assertAggregate(Conversation $conversation, ConversationMessage $message, RuntimeRun $run): AgentContractVersion
    {
        $version = $conversation->agentVersion()->with('contractVersion.contract')->firstOrFail();
        $contract = $version->contractVersion;
        if (! $contract || ! $contract->contract
            || (int) $version->agent_id !== (int) $conversation->agent_id
            || (int) $contract->contract->agent_id !== (int) $conversation->agent_id
            || (int) $message->conversation_id !== (int) $conversation->id
            || $message->role !== ConversationMessageRole::Assistant
            || $message->status !== ConversationMessageStatus::Completed
            || (int) $message->runtime_run_id !== (int) $run->id
            || (int) $run->agent_id !== (int) $conversation->agent_id
            || (int) $run->agent_version_id !== (int) $conversation->agent_version_id
            || $run->status !== RuntimeRunStatus::Completed) {
            throw new \DomainException('Lead outcome aggregate references are inconsistent.');
        }

        return $contract;
    }

    private function assertLead(Lead $lead, Conversation $conversation, AgentContractVersion $contract): void
    {
        if ((int) $lead->conversation_id !== (int) $conversation->id
            || (int) $lead->agent_id !== (int) $conversation->agent_id
            || (int) $lead->agent_version_id !== (int) $conversation->agent_version_id
            || (int) $lead->agent_contract_version_id !== (int) $contract->id) {
            throw new \DomainException('Canonical Lead references are inconsistent.');
        }
    }

    private function normalize(array $candidate, LeadOutcomePolicy $policy): array
    {
        $unknown = array_diff(array_keys($candidate), $policy->allowedFields);
        if ($unknown !== []) {
            throw new \DomainException('The lead candidate contains fields not authorized by the contract.');
        }
        $data = [];
        foreach ($candidate as $key => $value) {
            if (! is_string($value)) {
                throw new \DomainException('The lead candidate contains an invalid value.');
            }
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
            if ($value === '' || mb_strlen($value) > $policy->maxLength || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new \DomainException('The lead candidate contains an invalid value.');
            }
            $data[$key] = $value;
        }

        return $data;
    }

    private function outcome(Conversation $c, ConversationMessage $m, RuntimeRun $r, AgentContractVersion $cv, ?Lead $l, OutcomeType $type, OutcomeStatus $status, array $evidence): void
    {
        if (OutcomeEvent::query()->where('conversation_id', $c->id)->where('outcome_type', $type->value)->where('runtime_run_id', $r->id)->exists()) {
            return;
        }
        $event = new OutcomeEvent;
        $event->agent_id = $c->agent_id;
        $event->agent_version_id = $c->agent_version_id;
        $event->agent_contract_version_id = $cv->id;
        $event->conversation_id = $c->id;
        $event->source_message_id = $m->id;
        $event->runtime_run_id = $r->id;
        $event->lead_id = $l?->id;
        $event->outcome_type = $type;
        $event->status = $status;
        $event->evidence = $evidence;
        $event->detected_at = now();
        $event->verified_at = $status === OutcomeStatus::Verified ? now() : null;
        $event->save();
    }
}
