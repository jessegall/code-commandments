<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\BareStatePredicateDetector;
use JesseGall\CodeCommandments\Detectors\Backend\NarratedCommandDetector;
use JesseGall\CodeCommandments\Support\VerbMood;
use PHPUnit\Framework\TestCase;

/**
 * A command is an order, a state predicate is a question — and the rule only ever speaks when the AST
 * and a known verb agree, so a plural-noun getter is never mistaken for narration.
 */
final class MethodMoodDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function narrated(string $code): array
    {
        return array_map(static fn ($m): string => $m->methodName(), (new NarratedCommandDetector)->find(Codebase::fromString($code)));
    }

    /**
     * @return list<string>
     */
    private function predicates(string $code): array
    {
        return array_map(static fn ($m): string => $m->methodName(), (new BareStatePredicateDetector)->find(Codebase::fromString($code)));
    }

    public function test_flags_a_void_command_narrated_in_the_third_person(): void
    {
        $code = <<<'PHP'
        <?php
        class Panel
        {
            public function hides(): void {}
            public function entersTestMode(): void {}
            public function announces(string $line): void {}
        }
        PHP;

        $this->assertSame(['hides', 'entersTestMode', 'announces'], $this->narrated($code));
    }

    public function test_flags_a_fluent_command_that_narrates(): void
    {
        $code = <<<'PHP'
        <?php
        class Drag
        {
            public function carries(array $payload): static { return $this; }
        }
        PHP;

        $this->assertSame(['carries'], $this->narrated($code));
    }

    public function test_leaves_an_imperative_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Panel
        {
            public function hide(): void {}
            public function enterTestMode(): void {}
            public function openFor(string $user): void {}
        }
        PHP;

        $this->assertSame([], $this->narrated($code));
    }

    public function test_leaves_a_plural_noun_getter_and_an_s_ending_verb_alone(): void
    {
        // The two shapes that make a bare "-s" test useless: nouns, and imperatives that end in s.
        $code = <<<'PHP'
        <?php
        class Registry
        {
            /** @return list<string> */
            public function names(): array { return []; }

            /** @return list<string> */
            public function bindings(): array { return []; }

            public function process(): void {}
            public function dismiss(): void {}
            public function focus(): void {}
        }
        PHP;

        $this->assertSame([], $this->narrated($code));
        $this->assertSame([], $this->predicates($code));
    }

    public function test_leaves_a_static_named_constructor_alone(): void
    {
        // `ScanResult::requiresSwitch(...)` names what it builds; it was never an order.
        $code = <<<'PHP'
        <?php
        class ScanResult
        {
            public static function requiresSwitch(string $code): self { return new self(); }
        }
        PHP;

        $this->assertSame([], $this->narrated($code));
    }

    public function test_leaves_a_fluent_constraint_that_relates_the_receiver_to_its_argument(): void
    {
        // `->startsWith('a')` states a constraint; the third person is the correct English for it.
        $code = <<<'PHP'
        <?php
        class StringRule
        {
            public function startsWith(string $value): static { return $this; }
            public function endsWith(string $value): static { return $this; }
            public function compliesWith(callable $check): static { return $this; }
        }
        PHP;

        $this->assertSame([], $this->narrated($code));
    }

    public function test_a_void_command_with_a_preposition_is_still_an_order(): void
    {
        $code = <<<'PHP'
        <?php
        class Orchestrator
        {
            public function runsOn(string $secret): void {}
        }
        PHP;

        $this->assertSame(['runsOn'], $this->narrated($code));
    }

    public function test_flags_a_no_argument_bool_named_as_a_bare_verb(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            public function binds(): bool { return true; }
            public function spins(): bool { return true; }
        }
        PHP;

        $this->assertSame(['binds', 'spins'], $this->predicates($code));
    }

    public function test_leaves_a_relational_predicate_alone(): void
    {
        // It takes what it is compared with, so it is already a sentence: "the set contains x".
        $code = <<<'PHP'
        <?php
        class Set
        {
            public function contains(string $item): bool { return true; }
            public function matches(string $pattern): bool { return true; }
            public function covers(string $range): bool { return true; }
        }
        PHP;

        $this->assertSame([], $this->predicates($code));
    }

    public function test_leaves_a_question_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            public function isBound(): bool { return true; }
            public function hasParent(): bool { return true; }
            public function canRetry(): bool { return true; }
            public function awaitsAnswer(): bool { return true; }
        }
        PHP;

        $this->assertSame([], $this->predicates($code));
    }

    public function test_leaves_a_name_the_author_did_not_choose(): void
    {
        // An interface's word, not the class's — renaming it would break the contract.
        $code = <<<'PHP'
        <?php
        interface Rule { public function passes(string $attribute): bool; }
        class MaxLength implements Rule
        {
            public function passes(string $attribute): bool { return true; }
        }
        PHP;

        $this->assertSame([], $this->predicates($code));
        $this->assertSame([], $this->narrated($code));
    }

    public function test_leaves_a_magic_method_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            public function __invoke(): void {}
        }
        PHP;

        $this->assertSame([], $this->narrated($code));
    }

    public function test_the_lexicon_offers_the_imperative_it_should_have_worn(): void
    {
        $this->assertSame('hide', VerbMood::imperative('hides'));
        $this->assertSame('enterTestMode', VerbMood::imperative('entersTestMode'));
        $this->assertSame('carry', VerbMood::imperative('carries'));
        $this->assertSame('push', VerbMood::imperative('pushes'));
        $this->assertSame('bindings', VerbMood::imperative('bindings'), 'an unknown stem is handed back untouched');
        $this->assertSame('process', VerbMood::imperative('process'), 'an imperative that merely ends in s is untouched');

        // `names` is genuinely ambiguous — a verb AND a plural noun — and the lexicon cannot settle it.
        // That is the AST's job: `names(): array` is a getter, so no rule ever asks about its mood.
        $this->assertSame('name', VerbMood::imperative('names'));
    }
}
