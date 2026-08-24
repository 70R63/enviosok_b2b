<?php

namespace App\Domain\AI\Actions\Support;

final class ActionSchemaValidator
{
    public function validate(array $value, array $schema, int $maxBytes): void
    {
        if (strlen(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > $maxBytes) {
            throw new \DomainException('Action data exceeds the configured limit.');
        }
        $this->node($value, $schema);
    }

    private function node(mixed $value, array $schema): void
    {
        $types = (array) $schema['type'];
        $type = match (true) {
            is_array($value) && ! array_is_list($value) => 'object',
            is_array($value) => 'array',
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            $value === null => 'null',
            default => 'invalid',
        };
        if (! in_array($type, $types, true) && ! ($type === 'integer' && in_array('number', $types, true))) {
            throw new \DomainException('Action data does not match its trusted schema.');
        }
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            throw new \DomainException('Action data does not match its trusted schema.');
        }
        if ($type === 'object') {
            if (array_diff(array_keys($value), array_keys($schema['properties'])) !== [] || array_diff($schema['required'], array_keys($value)) !== []) {
                throw new \DomainException('Action data does not match its trusted schema.');
            }
            foreach ($value as $key => $child) {
                $this->node($child, $schema['properties'][$key]);
            }
        } elseif ($type === 'array') {
            foreach ($value as $child) {
                $this->node($child, $schema['items']);
            }
        }
    }
}
