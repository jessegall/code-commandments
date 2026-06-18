<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\DemeterEndpointReachProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class DemeterEndpointReachProphetTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-demeter-' . uniqid();
        @mkdir($this->dir, 0755, true);

        file_put_contents($this->dir . '/WorkflowEdge.php', <<<'PHP'
        <?php
        namespace App;
        class Endpoint { public string $nodeId; public string $port; }
        class WorkflowEdge {
            public Endpoint $from; public Endpoint $to;
            public function leaves(string $id): bool { return $this->from->nodeId === $id; }
            public function enters(string $id): bool { return $this->to->nodeId === $id; }
        }
        PHP);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function judgeGraph(string $php): \JesseGall\CodeCommandments\Results\Judgment
    {
        $graph = $this->dir . '/Graph.php';
        file_put_contents($graph, $php);

        $index = CodebaseIndex::build([$this->dir . '/WorkflowEdge.php', $graph]);
        $prophet = new DemeterEndpointReachProphet();
        $prophet->setCodebaseIndex($index);

        return $prophet->judge($graph, file_get_contents($graph));
    }

    public function test_flags_reach_through_endpoint_when_owner_has_intent_method(): void
    {
        $j = $this->judgeGraph(<<<'PHP'
        <?php
        namespace App;
        class Graph {
            public function find(WorkflowEdge $edge, string $nodeId): bool {
                return $edge->to->nodeId === $nodeId;
            }
        }
        PHP);

        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('WorkflowEdge already exposes intent methods', $j->warnings[0]->message);
    }

    public function test_does_not_flag_a_single_hop(): void
    {
        $j = $this->judgeGraph(<<<'PHP'
        <?php
        namespace App;
        class Graph {
            public function find(Endpoint $end, string $id): bool {
                return $end->nodeId === $id;
            }
        }
        PHP);

        $this->assertFalse($j->hasWarnings());
    }

    public function test_stays_silent_without_an_index(): void
    {
        $prophet = new DemeterEndpointReachProphet();
        $content = "<?php\nnamespace App;\nclass G { public function f(WorkflowEdge \$e, string \$id): bool { return \$e->to->nodeId === \$id; } }\n";

        $this->assertTrue($prophet->judge('/g.php', $content)->isRighteous());
    }
}
