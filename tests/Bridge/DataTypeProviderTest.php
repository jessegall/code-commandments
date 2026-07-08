<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Bridge;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Bridge\Backend\DataTypeProvider;
use JesseGall\CodeCommandments\Bridge\TypeContract;
use PHPUnit\Framework\TestCase;

final class DataTypeProviderTest extends TestCase
{
    public function test_it_publishes_a_contract_per_data_class_by_short_name_and_public_fields(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php
        namespace App\Data;
        use Spatie\LaravelData\Data;

        final class OrderData extends Data
        {
            public function __construct(
                public int $id,
                public string $total,
                public string $placedAt,
            ) {}
        }
        PHP);

        $contracts = (new DataTypeProvider())->contracts($codebase);

        $this->assertCount(1, $contracts);
        $this->assertSame('OrderData', $contracts[0]->name);
        $this->assertSame(['id', 'total', 'placedAt'], $contracts[0]->fields);
    }

    public function test_it_reads_public_declared_properties_too(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php
        namespace App\Data;
        use Spatie\LaravelData\Data;

        final class CustomerData extends Data
        {
            public string $firstName;
            public string $lastName;
            protected string $internalNote;
        }
        PHP);

        $contracts = (new DataTypeProvider())->contracts($codebase);

        $this->assertSame('CustomerData', $contracts[0]->name);
        $this->assertSame(['firstName', 'lastName'], $contracts[0]->fields);
    }

    public function test_it_marks_optional_backed_fields_as_optional_on_the_contract(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php
        namespace App\Data;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Optional;

        final class OrderData extends Data
        {
            public function __construct(
                public int $id,
                public string $total,
                public Chrome|Optional $chrome = new Optional(),
            ) {}
        }
        PHP);

        $contracts = (new DataTypeProvider())->contracts($codebase);

        $this->assertSame(['id', 'total', 'chrome'], $contracts[0]->fields);
        $this->assertSame(['chrome'], $contracts[0]->optionalFields);
    }

    public function test_a_plain_class_that_does_not_extend_data_publishes_nothing(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php
        namespace App;

        final class OrderViewModel
        {
            public function __construct(public int $id, public string $total) {}
        }
        PHP);

        $this->assertSame([], (new DataTypeProvider())->contracts($codebase));
    }
}
