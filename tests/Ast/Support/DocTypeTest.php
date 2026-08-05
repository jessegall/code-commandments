<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\DocType;
use PHPUnit\Framework\TestCase;

/**
 * {@see DocType} reads the type PHP itself cannot declare — the ELEMENT of a collection — out of a
 * docblock, and resolves the name it finds the way the file's own code would (#449).
 */
final class DocTypeTest extends TestCase
{
    public function test_it_reads_the_element_of_every_collection_notation(): void
    {
        $this->assertSame('Payment', DocType::elementOf('list<Payment>'));
        $this->assertSame('Payment', DocType::elementOf('Payment[]'));
        $this->assertSame('Payment', DocType::elementOf('array<string, Payment>'));
        $this->assertSame('Payment', DocType::elementOf('Collection<int, Payment>'));
        $this->assertSame('App\Payment', DocType::elementOf('iterable<\App\Payment>'));
    }

    public function test_a_collection_of_scalars_and_a_plain_type_hold_no_element(): void
    {
        $this->assertNull(DocType::elementOf('list<string>'));
        $this->assertNull(DocType::elementOf('array<string, int>'));
        $this->assertNull(DocType::elementOf('Payment'), 'a plain class is not a collection');
        $this->assertNull(DocType::elementOf('string'));
    }

    public function test_it_takes_the_tag_that_names_the_variable_asked_about(): void
    {
        // A promoted constructor param is documented alongside its siblings, so the block holds several.
        $docblock = <<<'DOC'
        /**
         * @param  list<Line>  $lines
         * @param  list<Payment>  $payments
         */
        DOC;

        $this->assertSame('Payment', DocType::elementNamed($docblock, 'payments'));
        $this->assertSame('Line', DocType::elementNamed($docblock, 'lines'));
        $this->assertNull(DocType::elementNamed($docblock, 'total'));
    }

    public function test_a_name_resolves_through_the_files_own_imports(): void
    {
        $file = Codebase::fromString(<<<'PHP'
        <?php
        namespace App\Services;

        use App\Records\PaymentData;
        use App\Records\LineData as Line;
        use App\Support\{Money, Cents};

        class Svc {}
        PHP)->files()[0];

        $this->assertSame('App\Records\PaymentData', DocType::resolve('PaymentData', $file), 'an import wins');
        $this->assertSame('App\Records\LineData', DocType::resolve('Line', $file), 'under the alias it is known by');
        $this->assertSame('App\Support\Cents', DocType::resolve('Cents', $file), 'a group import too');
        $this->assertSame('App\Services\Local', DocType::resolve('Local', $file), 'else the file\'s own namespace');
        $this->assertSame('Other\Thing', DocType::resolve('\Other\Thing', $file), 'a qualified name stands');
    }
}
