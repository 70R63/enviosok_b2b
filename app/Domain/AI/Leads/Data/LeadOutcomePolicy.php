<?php

namespace App\Domain\AI\Leads\Data;

use App\Domain\AI\Support\StructuredDataGuard;

final readonly class LeadOutcomePolicy
{
    private const FIELDS = ['name', 'email', 'phone', 'company', 'interest'];

    public function __construct(
        public array $allowedFields,
        public array $requiredFields,
        public array $outcomes,
        public int $maxLength,
    ) {}

    public static function from(array $policy): self
    {
        StructuredDataGuard::validate($policy);
        $lead = $policy['lead'] ?? [];
        if (! is_array($lead) || ($lead !== [] && array_is_list($lead))) {
            throw new \DomainException('The contract lead policy is invalid.');
        }
        $allowed = self::stringList($lead['allowed_fields'] ?? []);
        $required = self::stringList($lead['required_fields'] ?? []);
        $outcomes = self::stringList($policy['outcomes'] ?? []);
        if (array_diff($allowed, self::FIELDS) !== [] || array_diff($required, $allowed) !== [] || array_diff($outcomes, ['valid_lead', 'resolved_consultation']) !== []) {
            throw new \DomainException('The contract outcome policy contains unsupported values.');
        }
        $max = $lead['max_length'] ?? 191;
        if (! is_int($max) || $max < 1 || $max > 500) {
            throw new \DomainException('The contract lead field limit is invalid.');
        }

        return new self($allowed, $required, $outcomes, $max);
    }

    public function permits(string $outcome): bool
    {
        return in_array($outcome, $this->outcomes, true);
    }

    private static function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new \DomainException('The contract outcome policy must use lists.');
        }
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new \DomainException('The contract outcome policy contains an invalid item.');
            }
        }

        return array_values(array_unique($value));
    }
}
