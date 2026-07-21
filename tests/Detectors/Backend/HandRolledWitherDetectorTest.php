<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\HandRolledWitherDetector;
use PHPUnit\Framework\TestCase;

final class HandRolledWitherDetectorTest extends TestCase
{
    /**
     * The detector reads the project's `require.php` from disk, so the fixture is written to a temp
     * dir with a composer.json stating the PHP target.
     *
     * @return list<string>
     */
    private function scopes(string $php, string $target = '^8.5'): array
    {
        $dir = sys_get_temp_dir() . '/wither-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/composer.json', json_encode(['require' => ['php' => $target]]));
        file_put_contents($dir . '/Fixture.php', $php);

        try {
            return array_map(
                static fn ($m): string => $m->scope(),
                (new HandRolledWitherDetector)->find(Codebase::scan($dir)),
            );
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    private const SOCKET = <<<'PHP'
    <?php
    namespace App;

    final readonly class InputSocket
    {
        public function __construct(
            public string $id,
            public string $label,
            public string $type,
            public bool $required,
            public ?string $value,
            public int $order,
        ) {}

        public function withValue(?string $value): self
        {
            return new self($this->id, $this->label, $this->type, $this->required, $value, $this->order);
        }

        public function renamed(string $label, int $order): self
        {
            return new self($this->id, $label, $this->type, $this->required, $this->value, $order);
        }

        public function copy(): self
        {
            return new self($this->id, $this->label, $this->type, $this->required, $this->value, $this->order);
        }

        public function describe(): string
        {
            return $this->label . ':' . $this->type;
        }
    }
    PHP;

    public function test_flags_withers_but_not_a_plain_copy(): void
    {
        // `copy()` changes nothing, so there is no wither to collapse — with no changed argument it is
        // a duplicate, not a re-threaded field list.
        $this->assertSame(
            ['App\\InputSocket::withValue', 'App\\InputSocket::renamed'],
            $this->scopes(self::SOCKET),
        );
    }

    public function test_says_nothing_on_a_project_that_cannot_use_clone_with(): void
    {
        // The fix is PHP 8.5 clone-with. On an 8.3 project it would not compile, so the rule is silent.
        $this->assertSame([], $this->scopes(self::SOCKET, '^8.3'));
    }

    public function test_a_constructor_with_a_body_is_left_alone(): void
    {
        // `new self(…)` RUNS the constructor; `clone($this, […])` does NOT. A constructor that
        // validates would be silently skipped, so rewriting here would change behaviour.
        $php = <<<'PHP'
        <?php
        namespace App;

        final readonly class Money
        {
            public function __construct(
                public int $cents,
                public string $currency,
                public string $locale,
                public bool $rounded,
            ) {
                if ($cents < 0) {
                    throw new \InvalidArgumentException('negative');
                }
            }

            public function withCents(int $cents): self
            {
                return new self($cents, $this->currency, $this->locale, $this->rounded);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($php));
    }

    public function test_a_short_rebuild_is_below_the_floor(): void
    {
        // Two fields carried across is an ordinary constructor call, not a maintenance tax.
        $php = <<<'PHP'
        <?php
        namespace App;

        final readonly class Pair
        {
            public function __construct(public int $left, public int $right, public string $label) {}

            public function withLeft(int $left): self
            {
                return new self($left, $this->right, $this->label);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($php));
    }
}
