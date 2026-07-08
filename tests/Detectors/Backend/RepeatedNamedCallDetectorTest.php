<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RepeatedNamedCallDetector;
use PHPUnit\Framework\TestCase;

/**
 * A `with`-style (variadic) method called with the same named argument + construction boilerplate at 2+
 * sites is a missing helper. A one-off, differing named arguments, a non-variadic method, a trivial value,
 * and unrelated classes are all spared. Inherited calls on subclasses of one base group together.
 */
final class RepeatedNamedCallDetectorTest extends TestCase
{
    private function hits(string $php): int
    {
        return count(new RepeatedNamedCallDetector()->find(Codebase::fromString($php)));
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Meta extends Data { public function __construct(public readonly string $name) {} }
        class Element {
            public function copyWith(mixed ...$changes): static { return $this; }
        }
        PHP;

    private function code(string $body): string
    {
        return self::HEADER . "\n" . $body;
    }

    public function test_flags_the_same_named_construction_call_repeated(): void
    {
        $this->assertSame(2, $this->hits($this->code(<<<'PHP'
            final class Builder {
                public function a(Element $el): Element {
                    return $el->copyWith(metadata: Meta::from(['name' => 'a'])->toArray());
                }
                public function b(Element $el): Element {
                    return $el->copyWith(metadata: Meta::from(['name' => 'b'])->toArray());
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_one_off(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Builder {
                public function a(Element $el): Element {
                    return $el->copyWith(metadata: Meta::from(['name' => 'a'])->toArray());
                }
            }
            PHP)));
    }

    public function test_does_not_flag_different_named_arguments(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Builder {
                public function a(Element $el): Element {
                    return $el->copyWith(metadata: Meta::from(['name' => 'a'])->toArray());
                }
                public function b(Element $el): Element {
                    return $el->copyWith(chrome: Meta::from(['name' => 'b'])->toArray());
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_non_variadic_method(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            class Plain {
                public function copyWith(array $changes): static { return $this; }
            }
            final class Builder {
                public function a(Plain $p): Plain {
                    return $p->copyWith(metadata: Meta::from(['name' => 'a'])->toArray());
                }
                public function b(Plain $p): Plain {
                    return $p->copyWith(metadata: Meta::from(['name' => 'b'])->toArray());
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_trivial_value(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Builder {
                public function a(Element $el, string $v): Element {
                    return $el->copyWith(metadata: $v);
                }
                public function b(Element $el, string $v): Element {
                    return $el->copyWith(metadata: $v);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_unrelated_classes(): void
    {
        // Two independent types with a copyWith — not the same operation, so no repetition.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            class Other {
                public function copyWith(mixed ...$changes): static { return $this; }
            }
            final class Builder {
                public function a(Element $el): Element {
                    return $el->copyWith(metadata: Meta::from(['name' => 'a'])->toArray());
                }
                public function b(Other $other): Other {
                    return $other->copyWith(metadata: Meta::from(['name' => 'b'])->toArray());
                }
            }
            PHP)));
    }

    public function test_groups_inherited_calls_on_subclasses_of_one_base(): void
    {
        $this->assertSame(2, $this->hits($this->code(<<<'PHP'
            class Card extends Element {}
            class Port extends Element {}
            final class Builder {
                public function a(Card $card): Card {
                    return $card->copyWith(metadata: Meta::from(['name' => 'a'])->toArray());
                }
                public function b(Port $port): Port {
                    return $port->copyWith(metadata: Meta::from(['name' => 'b'])->toArray());
                }
            }
            PHP)));
    }
}
