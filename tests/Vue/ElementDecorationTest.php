<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use PHPUnit\Framework\TestCase;

/**
 * The frontend query injects a decorator node the SAME way the backend does — a `where` closure
 * that type-hints an {@see ElementMatch} subclass is handed that node (both engines run the shared
 * {@see \JesseGall\CodeCommandments\Query} base). This is the doctrine: one mechanism, two engines.
 */
final class ElementDecorationTest extends TestCase
{
    public function test_a_typed_closure_gets_the_element_decorator(): void
    {
        $flagged = Codebase::fromString('<template><div /><span /></template>')
            ->whereElement()
            ->where(static fn (DivNode $node): bool => $node->isDiv())
            ->get();

        $this->assertCount(1, $flagged, 'only the <div> matched the decorator predicate');
        $this->assertContainsOnlyInstancesOf(ElementMatch::class, $flagged, 'the returned match is the base node');
    }
}

/** A frontend decorator — a domain predicate composed from the engine's element helpers. */
final class DivNode extends ElementMatch
{
    public function isDiv(): bool
    {
        return strtolower($this->tag) === 'div';
    }
}
