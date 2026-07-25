<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Backend\ComputedBooleanArgumentDetector;
use PHPUnit\Framework\TestCase;

final class ComputedBooleanArgumentDetectorTest extends TestCase
{
    public function test_flags_a_bool_chooser_every_caller_derives_from_the_same_object(): void
    {
        $code = <<<'PHP'
        <?php
        class Editor {
            public function inZenMode(): bool { return true; }
            public function hasPanelOpen(): bool { return false; }
        }
        class CornerInset {
            public static function of(bool $tucked): string { return $tucked ? 'tight' : 'wide'; }
        }
        class Toolbar {
            public function inset(Editor $editor): string {
                return CornerInset::of($editor->inZenMode() || $editor->hasPanelOpen());
            }
        }
        class Gutter {
            public function inset(Editor $editor): string {
                return CornerInset::of($editor->inZenMode());
            }
        }
        PHP;

        $this->assertSame(['CornerInset::of'], $this->scopes($code));
    }

    public function test_a_literal_argument_is_not_a_computed_flag(): void
    {
        // `sortBy($key, descending: true)` decides nothing on the caller's behalf — the flag is the
        // caller's own choice, not an answer it went and fetched from an object it holds.
        $code = <<<'PHP'
        <?php
        class Order { public function isPaid(): bool { return true; } }
        class Label {
            public static function of(bool $bold): string { return $bold ? 'B' : 'n'; }
        }
        class A { public function go(): string { return Label::of(true); } }
        class B { public function go(): string { return Label::of(false); } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_lone_caller_cannot_drift(): void
    {
        // One call site holds the whole rule in one place already; there is nothing to disagree with.
        $code = <<<'PHP'
        <?php
        class Editor { public function inZenMode(): bool { return true; } }
        class CornerInset {
            public static function of(bool $tucked): string { return $tucked ? 'tight' : 'wide'; }
        }
        class Toolbar {
            public function inset(Editor $editor): string { return CornerInset::of($editor->inZenMode()); }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_callers_asking_different_types_are_not_one_shared_rule(): void
    {
        // Two subjects means no single object could have been passed instead — the flag really is
        // the common currency here, and the signature is honest.
        $code = <<<'PHP'
        <?php
        class Editor { public function inZenMode(): bool { return true; } }
        class Preview { public function isCompact(): bool { return false; } }
        class Inset {
            public static function of(bool $tight): string { return $tight ? 'a' : 'b'; }
        }
        class Toolbar { public function go(Editor $editor): string { return Inset::of($editor->inZenMode()); } }
        class Sidebar { public function go(Preview $preview): string { return Inset::of($preview->isCompact()); } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_setter_stores_the_flag_it_does_not_choose_with_it(): void
    {
        // Storing a bool is what a bool is FOR; the smell is a method whose whole answer is decided
        // by flags the callers computed. A sink is not a chooser.
        $code = <<<'PHP'
        <?php
        class Editor { public function inZenMode(): bool { return true; } }
        class Layout {
            private bool $tucked = false;
            public function setTucked(bool $tucked): void { $this->tucked = $tucked; }
        }
        class A { public function go(Editor $e, Layout $l): void { $l->setTucked($e->inZenMode()); } }
        class B { public function go(Editor $e, Layout $l): void { $l->setTucked($e->inZenMode()); } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_non_bool_parameter_means_the_signature_already_names_something(): void
    {
        // With a real parameter beside the flag the method is not a pure chooser — the object is
        // already (partly) in the signature, so this rule has nothing to say about it.
        $code = <<<'PHP'
        <?php
        class Editor { public function inZenMode(): bool { return true; } }
        class Inset {
            public static function of(string $side, bool $tight): string { return $tight ? $side : ''; }
        }
        class A { public function go(Editor $e): string { return Inset::of('left', $e->inZenMode()); } }
        class B { public function go(Editor $e): string { return Inset::of('right', $e->inZenMode()); } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_an_instance_chooser_counts_the_same_as_a_static_one(): void
    {
        $code = <<<'PHP'
        <?php
        class Editor {
            public function inZenMode(): bool { return true; }
            public function hasPanelOpen(): bool { return false; }
        }
        class Chrome {
            public function inset(bool $tucked): string { return $tucked ? 'tight' : 'wide'; }
        }
        class A { public function go(Editor $e, Chrome $c): string { return $c->inset($e->inZenMode()); } }
        class B { public function go(Editor $e, Chrome $c): string { return $c->inset($e->hasPanelOpen()); } }
        PHP;

        $this->assertSame(['Chrome::inset'], $this->scopes($code));
    }

    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = new ComputedBooleanArgumentDetector()->find(Codebase::fromString($code));
        $scopes = array_map(static fn (NodeMatch $m): string => $m->scope(), $hits);
        sort($scopes);

        return $scopes;
    }
}
