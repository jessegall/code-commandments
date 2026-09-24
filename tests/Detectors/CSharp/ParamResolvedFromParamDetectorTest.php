<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\ParamResolvedFromParamDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A method that takes a container and a key and first resolves one against the other only wanted what the key
 * names; the resolver itself, and a container the method needs whole, are something else.
 */
final class ParamResolvedFromParamDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string TYPES = "public sealed class Node\n{\n    public string Title { get; set; } = \"\";\n}\npublic sealed class Graph\n{\n    public Dictionary<string, Node> Nodes { get; } = new();\n    public Node Node(string id) => Nodes[id];\n}\npublic sealed class Workflow\n{\n    public Graph Graph { get; } = new();\n    public string Name { get; init; } = \"\";\n    public void Touch() { }\n}\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function methods(): array
    {
        return [
            'a lookup through a method' => ["public void Rename(Workflow workflow, string nodeId, string title)\n    {\n        var node = workflow.Graph.Node(nodeId);\n        node.Title = title;\n    }", ['MethodDeclaration Rename']],
            'a lookup through an indexer' => ["public string Label(Workflow workflow, string nodeId)\n    {\n        var node = workflow.Graph.Nodes[nodeId];\n        return $\"{workflow.Name}: {node.Title}\";\n    }", ['MethodDeclaration Label']],
            'a lookup called on the container itself' => ["public void Retitle(Graph graph, string nodeId, string title)\n    {\n        var node = graph.Node(nodeId);\n        node.Title = title;\n    }", ['MethodDeclaration Retitle']],
            'the resolver itself' => ["public Node Resolve(Workflow workflow, string nodeId)\n    {\n        var node = workflow.Graph.Node(nodeId);\n        if (node.Title == \"\")\n        {\n            throw new System.InvalidOperationException(nodeId);\n        }\n        return node;\n    }", []],
            'a container needed whole' => ["public void Rename(Workflow workflow, string nodeId, string title)\n    {\n        var node = workflow.Graph.Node(nodeId);\n        node.Title = title;\n        workflow.Touch();\n    }", []],
            'a library type applied to text' => ["public string Tag(System.Text.RegularExpressions.Regex tag, string html)\n    {\n        var match = tag.Match(html);\n        return match.Success ? match.Value : \"\";\n    }", []],
            'no key parameter' => ["public void Rename(Workflow workflow, Node other)\n    {\n        var node = workflow.Graph.Node(other.Title);\n        node.Title = \"x\";\n    }", []],
        ];
    }

    /**
     * @param  list<string>  $flagged
     */
    #[DataProvider('methods')]
    public function test_flags_a_method_that_unpacks_its_target_from_a_container_parameter(string $method, array $flagged): void
    {
        $source = self::TYPES . "public sealed class Editor\n{\n    {$method}\n}\n";
        $found = new ParamResolvedFromParamDetector()->find(Codebase::fromString($source, 'Editor.cs'));

        $this->assertSame($flagged, array_map(static fn ($match): string => $match->scope(), $found), $source);
    }
}
