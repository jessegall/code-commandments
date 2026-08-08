<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Testing\Comparison;
use JesseGall\CodeCommandments\Testing\Example;
use JesseGall\CodeCommandments\Language;
use PHPUnit\Framework\TestCase;

/**
 * A worked example is fenced and labelled by the language of the FIXTURE it came from, never by a
 * guess from the sin that points at it — a frontend rule's example is a template when it was marked
 * in a `.vue` file and a module when it was marked in a `.ts` one, and only the file knows which.
 */
final class ExampleLanguageTest extends TestCase
{
    public function test_the_language_is_read_off_the_fixture_file(): void
    {
        $this->assertSame(Language::Vue, Language::ofFile('components/OrderPanel.vue'));
        $this->assertSame(Language::TypeScript, Language::ofFile('types/orders.ts'));
        $this->assertSame(Language::Php, Language::ofFile('app/Orders/Order.php'));
    }

    public function test_an_example_carries_its_language_through_every_transformation(): void
    {
        $example = new Example(new Comparison('bad', 'good'))->in(Language::TypeScript);

        // `lifted` and `withBad` rebuild the example — a rebuild that dropped the language would
        // silently fence a TypeScript example as PHP.
        $this->assertSame(Language::TypeScript, $example->lifted(false)->language);
        $this->assertSame(Language::TypeScript, $example->lifted(true)->language);
        $this->assertSame(Language::TypeScript, $example->withBad('other')->language);
    }

    public function test_each_language_is_named_for_a_reader(): void
    {
        $this->assertSame('PHP', Language::Php->label());
        $this->assertSame('Vue', Language::Vue->label());
        $this->assertSame('TypeScript', Language::TypeScript->label());
    }
}
