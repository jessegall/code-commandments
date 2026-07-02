<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NearDuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

final class NearDuplicateFunctionDetectorTest extends TestCase
{
    public function test_flags_same_shape_methods_that_differ_only_in_names_and_literals(): void
    {
        $code = <<<'PHP'
        <?php
        class A {
            public function sumA(array $rows): int {
                $acc = 0;
                foreach ($rows as $r) {
                    if ($r > 0) { $acc += $r * 2; }
                }
                return $acc > 10 ? $acc : 0;
            }
            public function sumB(array $items): int {
                $total = 0;
                foreach ($items as $it) {
                    if ($it > 0) { $total += $it * 5; }
                }
                return $total > 20 ? $total : 0;
            }
            public function product(array $ns): string {
                return implode(',', array_map(static fn ($n) => (string) $n, $ns));
            }
        }
        PHP;

        $hits = (new NearDuplicateFunctionDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['A::sumA', 'A::sumB'], $scopes);
    }

    public function test_does_not_flag_declarative_manifests_that_share_a_shape(): void
    {
        // Reported (#259): every node's outputs() shares the socket-declaration shape. A pure
        // `return [ … ]` manifest DECLARES a shape — there's no logic to parameterise, and the
        // classes are independent — so a shared shape across them is not a near-duplicate.
        $code = <<<'PHP'
        <?php
        class OutputSocket { public static function make(string $n): self { return new self; } public function typed(string $t): self { return $this; } public function label(string $l): self { return $this; } public function describe(string $d): self { return $this; } }
        class LoadAccountNode {
            public function outputs(): array {
                return [
                    OutputSocket::make('account')->typed('account')->label('Account')->describe('the account'),
                    OutputSocket::make('error')->typed('string')->label('Error')->describe('the error'),
                    OutputSocket::make('status')->typed('bool')->label('Status')->describe('the status'),
                ];
            }
        }
        class SendEmailNode {
            public function outputs(): array {
                return [
                    OutputSocket::make('sent')->typed('bool')->label('Sent')->describe('was it sent'),
                    OutputSocket::make('message')->typed('string')->label('Message')->describe('the message'),
                    OutputSocket::make('failure')->typed('string')->label('Failure')->describe('the failure'),
                ];
            }
        }
        PHP;

        $this->assertSame([], (new NearDuplicateFunctionDetector)->find(Codebase::fromString($code)));
    }

    public function test_leaves_byte_identical_duplicates_to_the_exact_detector(): void
    {
        $code = <<<'PHP'
        <?php
        class A {
            public function run(int $x): int {
                $sum = 0;
                for ($i = 0; $i < $x; $i++) { $sum += $i * 2; }
                return $sum;
            }
        }
        class B {
            public function run(int $x): int {
                $sum = 0;
                for ($i = 0; $i < $x; $i++) { $sum += $i * 2; }
                return $sum;
            }
        }
        PHP;

        $this->assertSame([], (new NearDuplicateFunctionDetector)->find(Codebase::fromString($code)));
    }

    public function test_exempts_the_schema_declaration_hook_on_mcp_tool_subclasses(): void
    {
        // Two structurally-identical schema() hooks: flagged on a plain class, but exempt
        // when the class is an MCP Tool (schema() is its by-contract field declaration).
        // A schema() that BUILDS its map with logic (not a bare `return [ … ]` manifest), so this
        // isolates the by-contract exemption from the declarative-manifest skip.
        $body = static fn (string $a, string $b): string => <<<PHP
            public function schema(\$schema): array {
                \$fields = [];
                \$fields['{$a}'] = \$schema->string()->description('one')->required();
                \$fields['{$b}'] = \$schema->string()->description('two')->required();
                foreach (\$this->extra() as \$key) {
                    \$fields[\$key] = \$schema->string()->description('extra')->required();
                }
                \$fields['mode'] = \$schema->string()->description('four')->required();
                return \$fields;
            }
            PHP;

        $plain = <<<PHP
        <?php
        class ToolA { {$body('alpha', 'beta')} }
        class ToolB { {$body('gamma', 'delta')} }
        PHP;

        $onTool = <<<PHP
        <?php
        namespace Laravel\\Mcp\\Server { class Tool {} }
        namespace App {
            use Laravel\\Mcp\\Server\\Tool;
            class ToolA extends Tool { {$body('alpha', 'beta')} }
            class ToolB extends Tool { {$body('gamma', 'delta')} }
        }
        PHP;

        $plainHits = (new NearDuplicateFunctionDetector)->find(Codebase::fromString($plain));
        $toolHits = (new NearDuplicateFunctionDetector)->find(Codebase::fromString($onTool));

        // On a plain class the near-duplicate schema() pair IS flagged…
        $this->assertNotSame([], $plainHits);
        // …but on MCP Tool subclasses the by-contract hook is exempt.
        $this->assertSame([], $toolHits);
    }
}
