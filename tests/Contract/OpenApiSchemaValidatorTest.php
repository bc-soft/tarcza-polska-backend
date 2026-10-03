<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use PHPUnit\Framework\TestCase;

/** The checker must really fail on the mistakes it is meant to catch. */
final class OpenApiSchemaValidatorTest extends TestCase
{
    private OpenApiSchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OpenApiSchemaValidator([
            'Base' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'note' => ['type' => 'string', 'nullable' => true]]],
            'WithKind' => ['allOf' => [['$ref' => '#/components/schemas/Base'], ['type' => 'object', 'required' => ['kind'], 'properties' => ['kind' => ['type' => 'string', 'enum' => ['a']]]]]],
            'Other' => ['type' => 'object', 'required' => ['kind'], 'properties' => ['kind' => ['type' => 'string', 'enum' => ['b']]]],
            'Union' => ['oneOf' => [['$ref' => '#/components/schemas/WithKind'], ['$ref' => '#/components/schemas/Other']]],
        ]);
    }

    public function testAcceptsAValidAllOfObject(): void
    {
        self::assertSame([], $this->check('WithKind', ['id' => '0192f2b4-5a77-7a1e-9c3e-2a6f1d0b4c11', 'kind' => 'a', 'note' => null]));
    }

    public function testReportsMissingRequiredUndocumentedAndWrongType(): void
    {
        $violations = $this->check('WithKind', ['kind' => 'a', 'extra' => 1, 'note' => 5]);

        self::assertCount(3, $violations);
        self::assertStringContainsString('misses required "id"', $violations[0]);
        self::assertStringContainsString('extra is not documented', $violations[1]);
        self::assertStringContainsString('note should be a string', $violations[2]);
    }

    public function testNullOnlyWhereNullable(): void
    {
        self::assertSame(['$.id is null but not nullable'], $this->check('Base', ['id' => null]));
    }

    public function testEnumAndUuidFormat(): void
    {
        $violations = $this->check('WithKind', ['id' => 'not-a-uuid', 'kind' => 'z']);

        self::assertCount(2, $violations);
        self::assertStringContainsString('not a uuid', $violations[0]);
        self::assertStringContainsString('not in enum', $violations[1]);
    }

    public function testOneOfMustMatchExactlyOneBranch(): void
    {
        self::assertSame([], $this->check('Union', ['id' => '0192f2b4-5a77-7a1e-9c3e-2a6f1d0b4c11', 'kind' => 'a']));
        self::assertSame([], $this->check('Union', ['kind' => 'b']));
        self::assertCount(1, $this->check('Union', ['kind' => 'c']));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function check(string $schema, array $payload): array
    {
        return $this->validator->violations(['$ref' => '#/components/schemas/'.$schema], $payload);
    }
}
