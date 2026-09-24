<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\DeNulledFinderDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A finder returning a nullable reference whose every caller — two at least — de-nulls the result has its absence
 * decided in the wrong place; a finder some caller uses as maybe-missing, or a contract's signature, is not this.
 */
final class DeNulledFinderDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string TYPES = "#nullable enable\nusing System.Collections.Generic;\npublic sealed class Order\n{\n    public int Id { get; init; }\n    public string Email { get; init; } = \"\";\n}\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function sources(): array
    {
        $store = "public sealed class Orders\n{\n    private readonly Dictionary<int, Order> byId = new();\n    public Order? Find(int id) => byId.GetValueOrDefault(id);\n}\n";

        return [
            'every caller de-nulls' => [$store . "public sealed class Desk(Orders orders)\n{\n    public string Email(int id) => orders.Find(id)!.Email;\n    public Order Load(int id) => orders.Find(id) ?? throw new KeyNotFoundException();\n    public string Guarded(int id)\n    {\n        var order = orders.Find(id);\n        if (order is null)\n        {\n            throw new KeyNotFoundException();\n        }\n        return order.Email;\n    }\n}\n", ['MethodDeclaration Find']],
            'callers branching on the absence' => [$store . "public sealed class Desk(Orders orders)\n{\n    public bool Exists(int id) => orders.Find(id) is not null;\n    public string Email(int id) => orders.Find(id)?.Email ?? \"none\";\n}\n", []],
            'a caller keeps the maybe' => [$store . "public sealed class Desk(Orders orders)\n{\n    public string Email(int id) => orders.Find(id)!.Email;\n    public Order? Maybe(int id) => orders.Find(id);\n}\n", []],
            'a caller testing for presence' => [$store . "public sealed class Desk(Orders orders)\n{\n    public string Email(int id) => orders.Find(id)!.Email;\n    public Order Load(int id) => orders.Find(id) ?? throw new KeyNotFoundException();\n    public bool Known(int id) => orders.Find(id) is not null;\n}\n", []],
            'one caller' => [$store . "public sealed class Desk(Orders orders)\n{\n    public string Email(int id) => orders.Find(id)!.Email;\n}\n", []],
            'a contract signature' => ["public interface IOrders\n{\n    Order? Find(int id);\n}\npublic sealed class Orders : IOrders\n{\n    private readonly Dictionary<int, Order> byId = new();\n    public Order? Find(int id) => byId.GetValueOrDefault(id);\n}\npublic sealed class Desk(Orders orders)\n{\n    public string Email(int id) => orders.Find(id)!.Email;\n    public Order Load(int id) => orders.Find(id) ?? throw new KeyNotFoundException();\n}\n", []],
        ];
    }

    /**
     * @param  list<string>  $flagged
     */
    #[DataProvider('sources')]
    public function test_flags_a_finder_every_caller_de_nulls(string $source, array $flagged): void
    {
        $found = new DeNulledFinderDetector()->find(Codebase::fromString(self::TYPES . $source, 'Desk.cs'));

        $this->assertSame($flagged, array_map(static fn ($match): string => $match->scope(), $found), $source);
    }
}
