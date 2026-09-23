<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Vocabulary;
use PHPUnit\Framework\TestCase;

/**
 * One bridge read holds each resolved type and call target once, however many nodes name it.
 */
final class VocabularyTest extends TestCase
{
    public function test_a_type_named_twice_is_one_instance(): void
    {
        $vocabulary = new Vocabulary();

        $this->assertSame($vocabulary->type('global::System.String', true), $vocabulary->type('global::System.String', true));
        $this->assertNotSame($vocabulary->type('global::System.String', true), $vocabulary->type('global::System.String', false));
    }

    public function test_a_target_called_twice_is_one_instance(): void
    {
        $vocabulary = new Vocabulary();

        $this->assertSame($vocabulary->target('global::Shop.Cart', 'Add', ['global::System.Int32']), $vocabulary->target('global::Shop.Cart', 'Add', ['global::System.Int32']));
        $this->assertNotSame($vocabulary->target('global::Shop.Cart', 'Add', ['global::System.Int32']), $vocabulary->target('global::Shop.Cart', 'Add', []));
    }
}
