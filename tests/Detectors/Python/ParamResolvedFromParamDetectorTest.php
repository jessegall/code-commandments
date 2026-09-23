<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ParamResolvedFromParamDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class ParamResolvedFromParamDetectorTest extends TestCase
{
    private const string MODEL = <<<'PY'
        from enum import Enum


        class Node:
            title: str = ""


        class Graph:
            def node(self, node_id: str) -> Node:
                return Node()

            def edges(self, node_id: str, side: str) -> list[str]:
                return []


        class Workflow:
            graph: Graph
            nodes: dict[str, Node]


        class Rate(Enum):
            FAST = "fast"

            def cents(self, grams: int) -> int:
                return grams

        PY;

    public function test_flags_a_function_that_first_looks_its_id_up_in_the_container_it_was_handed(): void
    {
        $this->assertSame(['rename', 'retitle'], $this->flagged(<<<'PY'
            def rename(workflow: Workflow, node_id: str, title: str) -> None:
                node = workflow.graph.node(node_id)
                node.title = title


            def retitle(workflow: Workflow, node_id: str) -> str:
                node = workflow.nodes[node_id]
                return node.title.upper()
            PY));
    }

    public function test_leaves_the_resolver_a_co_subject_a_query_and_an_enum(): void
    {
        $this->assertSame([], $this->flagged(<<<'PY'
            def resolve(workflow: Workflow, node_id: str) -> Node:
                node = workflow.nodes.get(node_id)
                if node is None:
                    raise KeyError(node_id)
                return node


            def save(workflow: Workflow) -> None:
                pass


            def rename_and_save(workflow: Workflow, node_id: str) -> None:
                node = workflow.graph.node(node_id)
                node.title = "x"
                save(workflow)


            def outgoing(workflow: Workflow, node_id: str) -> int:
                edges = workflow.graph.edges(node_id, "out")
                return len(edges)


            def missing(workflow: Workflow, node_id: str) -> str:
                node = workflow.nodes[node_id]
                return f"{node_id}: {node.title}"


            def price(rate: Rate, grams: int) -> int:
                cents = rate.cents(grams)
                return cents * 2
            PY));
    }

    /**
     * @return list<string>
     */
    private function flagged(string $functions): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->name(), new ParamResolvedFromParamDetector()->find(Codebase::fromString(self::MODEL . "\n\n" . $functions)));
    }
}
