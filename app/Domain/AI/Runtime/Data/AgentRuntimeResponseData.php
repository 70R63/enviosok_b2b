<?php

namespace App\Domain\AI\Runtime\Data;

use App\Domain\AI\Runtime\Exceptions\InvalidModelResponseException;
use App\Domain\AI\Actions\Data\ActionRequestData;

final readonly class AgentRuntimeResponseData
{
    public const REASONS = ['none', 'insufficient_knowledge', 'low_confidence', 'human_requested', 'sensitive_action', 'policy_restriction'];

    public function __construct(public string $answer, public array $citationIds, public string $confidence, public bool $needsHandoff, public string $handoffReason, public array $sources = [], public ?int $runtimeRunId = null, public ?array $leadCandidate = null, public bool $resolvedCandidate = false, public ?ActionRequestData $actionRequest = null)
    {
        $answer = trim($answer);
        if ($answer === '' || mb_strlen($answer) > 1200 || preg_match('/[\x00-\x1F\x7F]/u', $answer) || preg_match('~https?://~iu', $answer) || ! in_array($confidence, ['high', 'medium', 'low'], true) || ! in_array($handoffReason, self::REASONS, true) || count($citationIds) > 10 || count($citationIds) !== count(array_unique($citationIds)) || (! $needsHandoff && ($citationIds === [] || $handoffReason !== 'none')) || ($needsHandoff && $handoffReason === 'none') || ($confidence === 'low' && ! $needsHandoff)) {
            throw new InvalidModelResponseException('The structured model response is invalid.');
        }
    }

    public static function validate(array $data, array $allowed): self
    {
        $base = ['answer', 'citation_ids', 'confidence', 'needs_handoff', 'handoff_reason'];
        $extended = [...$base, 'lead_candidate', 'resolved_candidate'];
        $withAction = [...$extended, 'action_request'];
        $actual = array_keys($data);
        sort($actual);
        sort($base);
        sort($extended); sort($withAction);
        if (! in_array($actual, [$base, $extended, $withAction], true) || ! is_string($data['answer'] ?? null) || ! is_array($data['citation_ids'] ?? null) || ! is_string($data['confidence'] ?? null) || ! is_bool($data['needs_handoff'] ?? null) || ! is_string($data['handoff_reason'] ?? null)) {
            throw new InvalidModelResponseException('The structured model response is invalid.');
        }foreach ($data['citation_ids'] as $id) {
            if (! is_string($id) || ! in_array($id, $allowed, true)) {
                throw new InvalidModelResponseException('The structured model response contains an invalid citation.');
            }
        }$candidate = $data['lead_candidate'] ?? null;
        if ($candidate !== null) {
            if (! is_array($candidate) || array_is_list($candidate) || array_keys($candidate) !== ['fields'] || ! is_array($candidate['fields']) || ! array_is_list($candidate['fields']) || count($candidate['fields']) > 5) {
                throw new InvalidModelResponseException('The lead candidate is invalid.');
            }$fields = [];
            foreach ($candidate['fields'] as $item) {
                if (! is_array($item) || array_keys($item) !== ['key', 'value'] || ! is_string($item['key']) || ! is_string($item['value']) || array_key_exists($item['key'], $fields)) {
                    throw new InvalidModelResponseException('The lead candidate is invalid.');
                }$fields[$item['key']] = $item['value'];
            }$candidate = $fields;
        }if (array_key_exists('resolved_candidate', $data) && ! is_bool($data['resolved_candidate'])) {
            throw new InvalidModelResponseException('The resolved candidate is invalid.');
        }

        $action = null;
        if (array_key_exists('action_request', $data) && $data['action_request'] !== null) {
            $request = $data['action_request'];
            if (! is_array($request) || array_is_list($request) || array_keys($request) !== ['action_key','arguments'] || ! is_string($request['action_key']) || ! is_array($request['arguments']) || array_is_list($request['arguments'])) throw new InvalidModelResponseException('The Action request is invalid.');
            try { $action = new ActionRequestData($request['action_key'], $request['arguments']); } catch (\InvalidArgumentException) { throw new InvalidModelResponseException('The Action request is invalid.'); }
        }
        return new self(trim($data['answer']), $data['citation_ids'], $data['confidence'], $data['needs_handoff'], $data['handoff_reason'], [], null, $candidate, (bool) ($data['resolved_candidate'] ?? false), $action);
    }
}
