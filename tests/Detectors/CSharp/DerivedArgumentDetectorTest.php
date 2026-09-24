<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\DerivedArgumentDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A call handing over an object and a projection of it, or an object in three pieces, where the method could
 * read them itself — when every call filling that parameter does the same.
 */
final class DerivedArgumentDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string TYPES = "public sealed class Request\n{\n    public int ChannelId { get; init; }\n    public string Sku { get; init; } = \"\";\n    public string Title { get; init; } = \"\";\n    public int Units { get; init; }\n}\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function sources(): array
    {
        $store = "public sealed class Store\n{\n    public void Persist(Request request, int channelId) { }\n    public void Label(string sku, string title, int units) { }\n}\n";

        return [
            'an object and a projection of it' => [$store . "public sealed class Flow(Store store)\n{\n    public void A(Request request) => store.Persist(request, request.ChannelId);\n    public void B(Request request) => store.Persist(request, request.ChannelId);\n}\n", [15, 16]],
            'an object in three pieces' => [$store . "public sealed class Flow(Store store)\n{\n    public void A(Request request) => store.Label(request.Sku, request.Title, request.Units);\n    public void B(Request other) => store.Label(other.Sku, other.Title, other.Units);\n}\n", [15, 16]],
            'one call does not derive it' => [$store . "public sealed class Flow(Store store)\n{\n    public void A(Request request) => store.Persist(request, request.ChannelId);\n    public void B(Request request) => store.Persist(request, 7);\n}\n", []],
            'a projection that differs from call to call' => ["public sealed class Store\n{\n    public void Persist(Request request, string text) { }\n}\npublic sealed class Flow(Store store)\n{\n    public void A(Request request) => store.Persist(request, request.Sku);\n    public void B(Request request) => store.Persist(request, request.Title);\n}\n", []],
            'a method also handed out as a delegate' => [$store . "public sealed class Flow(Store store)\n{\n    public void A(Request request) => store.Persist(request, request.ChannelId);\n    public void B(Request request) => store.Persist(request, request.ChannelId);\n    public System.Action<Request, int> Handler() => store.Persist;\n    public void Register(System.Action<System.Action<Request, int>> map) => map(store.Persist);\n}\n", []],
            'two pieces' => ["public sealed class Store\n{\n    public void Label(string sku, string title) { }\n}\npublic sealed class Flow(Store store)\n{\n    public void A(Request request) => store.Label(request.Sku, request.Title);\n}\n", []],
            'the receiver passing its own part' => ["public sealed class Box\n{\n    public int Size { get; init; }\n    public void Resize(int size) { }\n}\npublic sealed class Flow\n{\n    public void A(Box box) => box.Resize(box.Size);\n}\n", []],
        ];
    }

    /**
     * @param  list<int>  $lines
     */
    #[DataProvider('sources')]
    public function test_flags_a_parameter_its_callers_derive_from_what_they_already_pass(string $source, array $lines): void
    {
        $found = new DerivedArgumentDetector()->find(Codebase::fromString(self::TYPES . $source, 'Flow.cs'));

        $this->assertSame($lines, array_map(static fn (Located $match): int => $match->line(), $found), $source);
    }
}
