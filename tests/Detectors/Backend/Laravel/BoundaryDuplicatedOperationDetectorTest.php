<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\BoundaryDuplicatedOperationDetector;
use PHPUnit\Framework\TestCase;

final class BoundaryDuplicatedOperationDetectorTest extends TestCase
{
    private const DOMAIN = <<<'PHP'
    namespace Illuminate\Console { class Command { public function argument($k) {} } }
    namespace Laravel\Mcp\Server { class Tool {} }

    namespace App {
        class Minter { public function mint(string $n): string { return $n; } }
        class Aggregates { public function build(string $s): string { return $s; } }
        class Store { public function save(string $a): void {} }
        class Pipeline { public function execute(string $x): string { return $x; } }
    PHP;

    /** @return list<string> */
    private function scopes(string $body): array
    {
        $php = "<?php\n" . self::DOMAIN . "\n" . $body . "\n}\n";
        $scopes = array_map(
            static fn ($m): string => $m->scope(),
            (new BoundaryDuplicatedOperationDetector)->find(Codebase::fromString($php)),
        );
        sort($scopes);

        return $scopes;
    }

    public function test_flags_one_operation_wearing_two_faces(): void
    {
        $body = <<<'PHP'
        final class CreateWorkflowCommand extends \Illuminate\Console\Command {
            public function handle(Minter $minter, Aggregates $aggregates, Store $store): int {
                $slug = $minter->mint((string) $this->argument('name'));
                $store->save($aggregates->build($slug));

                return 0;
            }
        }

        final class CreateWorkflowTool extends \Laravel\Mcp\Server\Tool {
            public function handle(Minter $minter, Aggregates $aggregates, Store $store): string {
                $slug = $minter->mint('from-mcp');
                $built = $aggregates->build($slug);
                $store->save($built);

                return $slug;
            }
        }
        PHP;

        $this->assertSame(
            ['App\\CreateWorkflowCommand::handle', 'App\\CreateWorkflowTool::handle'],
            $this->scopes($body),
        );
    }

    public function test_two_faces_of_the_same_kind_are_not_this_sin(): void
    {
        // Two console commands sharing work is ordinary duplication, caught by the duplicate-function
        // rules. This sin is specifically about the operation spanning boundary KINDS, where nothing
        // forces the two to agree.
        $body = <<<'PHP'
        final class CreateOneCommand extends \Illuminate\Console\Command {
            public function handle(Minter $minter, Aggregates $aggregates, Store $store): int {
                $store->save($aggregates->build($minter->mint('a')));

                return 0;
            }
        }

        final class CreateTwoCommand extends \Illuminate\Console\Command {
            public function handle(Minter $minter, Aggregates $aggregates, Store $store): int {
                $store->save($aggregates->build($minter->mint('b')));

                return 0;
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($body));
    }

    public function test_shared_infrastructure_is_not_a_shared_operation(): void
    {
        // A pipeline runner is reached from every face. Counting it made every boundary in a real app
        // look duplicated, so a call used by many entry points is discounted as infrastructure.
        $body = <<<'PHP'
        final class AlphaCommand extends \Illuminate\Console\Command {
            public function handle(Pipeline $pipeline): int {
                $pipeline->execute('a'); $pipeline->execute('b'); $pipeline->execute('c');

                return 0;
            }
        }

        final class BetaTool extends \Laravel\Mcp\Server\Tool {
            public function handle(Pipeline $pipeline): string {
                $pipeline->execute('a'); $pipeline->execute('b'); $pipeline->execute('c');

                return 'ok';
            }
        }

        final class GammaCommand extends \Illuminate\Console\Command {
            public function handle(Pipeline $pipeline): int {
                $pipeline->execute('a'); $pipeline->execute('b'); $pipeline->execute('c');

                return 0;
            }
        }

        final class DeltaTool extends \Laravel\Mcp\Server\Tool {
            public function handle(Pipeline $pipeline): string {
                $pipeline->execute('a'); $pipeline->execute('b'); $pipeline->execute('c');

                return 'ok';
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($body));
    }
}
