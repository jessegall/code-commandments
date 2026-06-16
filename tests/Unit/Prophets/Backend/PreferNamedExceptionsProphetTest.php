<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferNamedExceptionsProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferNamedExceptionsProphetTest extends TestCase
{
    private PreferNamedExceptionsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferNamedExceptionsProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Generic SPL exceptions
    // ────────────────────────────────────────────────────────────────

    public function test_flags_generic_exception_with_interpolated_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw new RuntimeException("Missing required input '{$name}' on node '{$this->nodeId}'");
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('throw new RuntimeException in resolve()', $judgment->sins[0]->message);
        $this->assertStringContainsString('MissingRequiredInputException extends RuntimeException', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('MissingRequiredInputException::for(...)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_generic_exception_with_literal_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(): void {
            throw new InvalidArgumentException('missing name');
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('MissingNameException', $judgment->sins[0]->suggestion);
    }

    public function test_flags_generic_exception_without_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(): void {
            throw new RuntimeException;
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_fully_qualified_generic_exception(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(): void {
            throw new \LogicException('unreachable state');
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_generic_exception_with_sprintf_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw new RuntimeException(sprintf("Unknown port '%s'", $name));
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('UnknownPortException', $judgment->sins[0]->suggestion);
    }

    public function test_flags_generic_error_classes(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(mixed $value): void {
            throw new ValueError("Value out of range: {$value}");
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_throw_expression(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(?string $name): string {
            return $name ?? throw new RuntimeException('name not set');
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_does_not_flag_message_less_bad_method_call_guard(): void
    {
        // The canonical __call / __callStatic guard — the TYPE is the signal and
        // no message is assembled, so the named-exception rule does not apply.
        $judgment = $this->judgeClass(<<<'PHP'
        public function __call(string $name, array $arguments): mixed {
            throw new \BadMethodCallException();
        }
        PHP);

        $this->assertFalse(
            $judgment->isFallen(),
            'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
        );
    }

    public function test_flags_bad_method_call_with_message(): void
    {
        // A BadMethodCallException handed a message string is still a sin —
        // only the message-less guard is exempt.
        $judgment = $this->judgeClass(<<<'PHP'
        public function __call(string $name, array $arguments): mixed {
            throw new \BadMethodCallException("no such method {$name} on this class");
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Named exceptions with inline message strings
    // ────────────────────────────────────────────────────────────────

    public function test_flags_named_exception_with_interpolated_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw new PortNotWiredException("Port '{$name}' is not wired");
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('inline message string', $judgment->sins[0]->message);
        $this->assertStringContainsString('PortNotWiredException::for(...)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_named_exception_with_literal_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(): void {
            throw new PortNotWiredException('port is not wired');
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_named_exception_with_concatenated_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw new PortNotWiredException('Port ' . $name . ' is not wired');
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_named_message_argument(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw new PortNotWiredException(message: "Port '{$name}' is not wired");
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Righteous throw sites
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_static_factory_throw(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw MissingRequiredInputException::for($name, $this->nodeId);
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_factory_handed_a_message_string(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(object $reflection): void {
            throw InvalidPipeDefinitionException::make(
                $reflection->getName(),
                'SingleSubPipelineAdapter implementations must define mapTo()',
                'public function mapTo(): mixed',
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('InvalidPipeDefinitionException::make()', $judgment->sins[0]->message);
        $this->assertStringContainsString('built inside the exception', $judgment->sins[0]->message);
        $this->assertStringContainsString('Move the message string INSIDE', $judgment->sins[0]->suggestion);
    }

    public function test_flags_factory_handed_an_interpolated_message(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $name): void {
            throw PortNotWiredException::make("Port '{$name}' is not wired");
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_does_not_flag_factory_with_single_token_value(): void
    {
        // 'email' is a domain value (a field name), not a message.
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(): void {
            throw ValidationFailedException::forField('email');
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_factory_with_only_domain_values(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(object $reflection): void {
            throw InvalidPipeDefinitionException::missingMethod($reflection->getName(), 'mapTo');
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_named_exception_with_domain_values(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(object $port, object $node): void {
            throw new PortNotWiredException($port, $node);
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_rethrow(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(callable $fn): void {
            try {
                $fn();
            } catch (\Throwable $e) {
                throw $e;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_new_self_inside_factory(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use RuntimeException;
        final class MissingRequiredInputException extends RuntimeException {
            public static function for(string $port, string $nodeId): self {
                throw new self("Missing required input '{$port}' on node '{$nodeId}'");
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_own_class_name_inside_factory(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use RuntimeException;
        final class MissingRequiredInputException extends RuntimeException {
            public static function for(string $port): self {
                throw new MissingRequiredInputException("Missing required input '{$port}'");
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_new_static_inside_factory(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use RuntimeException;
        abstract class CompilationException extends RuntimeException {
            public static function for(string $detail): static {
                throw new static("Compilation failed: {$detail}");
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_allow_config(): void
    {
        $this->prophet->configure(['allow' => ['InvalidArgumentException']]);

        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(): void {
            throw new InvalidArgumentException('missing name');
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Multiple sins / counting
    // ────────────────────────────────────────────────────────────────

    public function test_flags_each_throw_site_separately(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function coerceInt(string $value): int {
            throw new RuntimeException("Cannot convert non-numeric string '{$value}' to int.");
        }

        public function coerceFloat(string $value): float {
            throw new RuntimeException("Cannot convert non-numeric string '{$value}' to float.");
        }
        PHP);

        $this->assertFallen($judgment, 2);
        $this->assertStringContainsString('CannotConvertNonNumericException', $judgment->sins[0]->suggestion);
    }

    public function test_reports_throw_line_number(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final class Resolver {
            public function resolve(): void {
                throw new \RuntimeException('boom');
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(5, $judgment->sins[0]->line);
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_dynamic_exception_class(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(string $exceptionClass): void {
            throw new $exceptionClass('message');
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_handles_empty_file(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php')->isRighteous());
    }

    public function test_handles_invalid_php_gracefully(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php this is not <<< valid')->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Description sanity
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertStringContainsString('static factories', $this->prophet->description());
        $this->assertStringContainsString('::for(', $this->prophet->detailedDescription());
        $this->assertStringContainsString('domain values', $this->prophet->detailedDescription());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judgeClass(string $members): Judgment
    {
        $content = <<<PHP
        <?php
        namespace App;
        use RuntimeException;
        use InvalidArgumentException;
        final class Resolver {
            private string \$nodeId = 'node-1';

            {$members}
        }
        PHP;

        return $this->prophet->judge('/x.php', $content);
    }

    private function assertFallen(Judgment $judgment, ?int $expectedSins = null): void
    {
        $this->assertTrue(
            $judgment->isFallen(),
            'Expected judgment to be fallen. Sins: ' . json_encode(array_map(
                fn ($s) => $s->message,
                $judgment->sins,
            ))
        );

        if ($expectedSins !== null) {
            $this->assertCount(
                $expectedSins,
                $judgment->sins,
                'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
            );
        }
    }
}
