<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ParamResolvedFromParamDetector;
use PHPUnit\Framework\TestCase;

final class ParamResolvedFromParamDetectorTest extends TestCase
{
    public function test_flags_a_method_that_unpacks_its_target_from_a_pure_container(): void
    {
        $code = <<<'PHP'
        <?php
        final class Graph { public function nodeById(string $id): object { return new \stdClass(); } }
        final class Workflow { public Graph $graph; public int $id = 0; }
        final class Service {
            public function run(Workflow $workflow, string $nodeId): void {
                $node = $workflow->graph->nodeById($nodeId);   // unpack the target
                $node->process();                              // ... and operate on it
            }
        }
        PHP;

        $hits = (new ParamResolvedFromParamDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Service::run'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_flags_an_unpack_chained_through_an_unwrap(): void
    {
        $code = <<<'PHP'
        <?php
        final class Repo { public function find(int $id): object { return new \stdClass(); } }
        final class Catalog { public Repo $repo; }
        final class Pricer {
            public function price(Catalog $catalog, int $sku): int {
                $item = $catalog->repo->find($sku)->orFail();
                return $item->price();
            }
        }
        PHP;

        $hits = (new ParamResolvedFromParamDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Pricer::price'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Http { class Request {} }
        namespace App {
            use Illuminate\Http\Request;

            final class Graph {
                public function nodeById(string $id): object { return new \stdClass(); }
                public function withMoved(object $n): Graph { return $this; }
            }
            final class Context { public function __construct(object $a, object $b) {} }

            // CO-SUBJECT: the graph is used as a whole object downstream (surgery on it),
            // so it isn't mere packaging.
            final class Surgeon {
                public function splice(Graph $graph, string $sourceId): Graph {
                    $source = $graph->nodeById($sourceId);
                    return $graph->withMoved($source);
                }
            }
            // CO-SUBJECT: the graph is passed whole into the result.
            final class Builder {
                public function build(Graph $graph, string $nodeId): Context {
                    $node = $graph->nodeById($nodeId);
                    return new Context($node, $graph);
                }
            }
            // BOUNDARY: a route arg arrives as a string; there is no caller to hand an object.
            final class Controller {
                public function __invoke(Request $request, Graph $graph, string $node): void {
                    $n = $graph->nodeById($node);
                    $n->process();
                }
            }
            // REGISTRY: keys into its OWN $this store — no container parameter at all.
            final class Registry {
                private array $items = [];
                public function for(string $type): object {
                    return $this->items[$type] ?? throw new \RuntimeException();
                }
            }
        }
        PHP;

        $this->assertSame([], (new ParamResolvedFromParamDetector)->find(Codebase::fromString($code)));
    }
}
