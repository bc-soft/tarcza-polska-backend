<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Minimal OpenAPI 3.0 schema checker for the contract test: $ref, allOf, oneOf, nullable, enum,
 * required, types and the formats we use. Objects are checked strictly: a key missing from
 * `properties` is a violation, so the spec cannot silently lag behind the views.
 */
final readonly class OpenApiSchemaValidator
{
    /** @param array<string, array<string, mixed>> $components components.schemas */
    public function __construct(private array $components)
    {
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<string> human-readable violations, empty when the value matches
     */
    public function violations(array $schema, mixed $value, string $path = '$'): array
    {
        $schema = $this->resolve($schema);

        if (isset($schema['oneOf'])) {
            $matches = 0;
            $details = [];
            /** @var array<string, mixed> $candidate */
            foreach ($schema['oneOf'] as $i => $candidate) {
                $v = $this->violations($candidate, $value, $path);
                if ([] === $v) {
                    ++$matches;
                } else {
                    $details[] = \sprintf('oneOf[%d]: %s', $i, implode('; ', $v));
                }
            }

            return 1 === $matches ? [] : [\sprintf('%s matched %d of oneOf (%s)', $path, $matches, implode(' | ', $details))];
        }

        if (null === $value) {
            return ($schema['nullable'] ?? false) ? [] : [\sprintf('%s is null but not nullable', $path)];
        }

        $errors = [];
        if (isset($schema['enum']) && !\in_array($value, $schema['enum'], true)) {
            $errors[] = \sprintf('%s = %s not in enum [%s]', $path, json_encode($value), implode(', ', array_map(strval(...), $schema['enum'])));
        }

        $type = $schema['type'] ?? null;
        if (null === $type) {
            return $errors;
        }

        switch ($type) {
            case 'object':
                if (!\is_array($value)) {
                    return [\sprintf('%s should be an object', $path)];
                }
                /** @var array<string, array<string, mixed>> $properties */
                $properties = $schema['properties'] ?? [];
                foreach ($schema['required'] ?? [] as $required) {
                    if (!\array_key_exists($required, $value)) {
                        $errors[] = \sprintf('%s misses required "%s"', $path, $required);
                    }
                }
                foreach ($value as $key => $item) {
                    if (!isset($properties[$key])) {
                        if (!isset($schema['additionalProperties'])) {
                            $errors[] = \sprintf('%s.%s is not documented', $path, $key);
                        }
                        continue;
                    }
                    $errors = [...$errors, ...$this->violations($properties[$key], $item, $path.'.'.$key)];
                }
                break;
            case 'array':
                if (!\is_array($value) || !array_is_list($value)) {
                    return [\sprintf('%s should be a list', $path)];
                }
                /** @var array<string, mixed> $items */
                $items = $schema['items'] ?? [];
                foreach ($value as $i => $item) {
                    $errors = [...$errors, ...$this->violations($items, $item, \sprintf('%s[%d]', $path, $i))];
                }
                break;
            case 'string':
                if (!\is_string($value)) {
                    return [\sprintf('%s should be a string', $path)];
                }
                $format = $schema['format'] ?? null;
                if ('date-time' === $format && false === DateTimeImmutable::createFromFormat(\DATE_ATOM, $value)) {
                    $errors[] = \sprintf('%s is not an ISO 8601 date-time: %s', $path, $value);
                }
                if ('uuid' === $format && !Uuid::isValid($value)) {
                    $errors[] = \sprintf('%s is not a uuid: %s', $path, $value);
                }
                break;
            case 'integer':
                if (!\is_int($value)) {
                    $errors[] = \sprintf('%s should be an integer', $path);
                }
                break;
            case 'number':
                if (!\is_int($value) && !\is_float($value)) {
                    $errors[] = \sprintf('%s should be a number', $path);
                }
                break;
            case 'boolean':
                if (!\is_bool($value)) {
                    $errors[] = \sprintf('%s should be a boolean', $path);
                }
                break;
        }

        return $errors;
    }

    /**
     * Follows $ref and flattens allOf into one object schema (properties, required, nullable, enum).
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function resolve(array $schema): array
    {
        if (isset($schema['$ref'])) {
            $name = substr((string) $schema['$ref'], \strlen('#/components/schemas/'));
            if (!isset($this->components[$name])) {
                throw new LogicException(\sprintf('Schema "%s" is referenced but not defined', $name));
            }
            $ref = $this->resolve($this->components[$name]);
            unset($schema['$ref']);

            return $this->merge($ref, $schema);
        }
        if (isset($schema['allOf'])) {
            $merged = [];
            /** @var array<string, mixed> $part */
            foreach ($schema['allOf'] as $part) {
                $merged = $this->merge($merged, $this->resolve($part));
            }
            unset($schema['allOf']);

            return $this->merge($merged, $schema);
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     *
     * @return array<string, mixed>
     */
    private function merge(array $a, array $b): array
    {
        $out = $a;
        foreach ($b as $key => $value) {
            if ('properties' === $key) {
                $out['properties'] = array_merge($a['properties'] ?? [], $value);
            } elseif ('required' === $key) {
                $out['required'] = array_values(array_unique([...($a['required'] ?? []), ...$value]));
            } elseif ('description' !== $key) {
                $out[$key] = $value;
            }
        }
        if (!isset($out['type']) && isset($out['properties'])) {
            $out['type'] = 'object';
        }

        return $out;
    }
}
