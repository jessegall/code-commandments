<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\RepeatedNamedCallDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The same `with` copy — one record, the same members set to the same constants — written at two or more
 * sites is an operation the record should name; a copy written once, and copies that set different values,
 * are fine.
 */
final class RepeatedNamedCallDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function bodies(): array
    {
        return [
            'the same copy twice' => ['public static Order A(Order o) => o with { Status = Status.Shipped }; public static Order B(Order o) => o with { Status = Status.Shipped };', 2],
            'the same copy, members in another order' => ['public static Order A(Order o) => o with { Status = Status.Shipped, Note = "sent" }; public static Order B(Order o) => o with { Note = "sent", Status = Status.Shipped };', 2],
            'the record naming it on itself' => ['public static Order A(Order o) => o.Shipped(); public static Order B(Order o) => o.Shipped();', 0],
            'written once' => ['public static Order A(Order o) => o with { Status = Status.Shipped };', 0],
            'different values' => ['public static Order A(Order o) => o with { Status = Status.Shipped }; public static Order B(Order o) => o with { Status = Status.Open };', 0],
            'a value worked out at the site' => ['public static Order A(Order o, string n) => o with { Note = n }; public static Order B(Order o, string n) => o with { Note = n };', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_the_same_copy_written_at_several_sites(string $body, int $flagged): void
    {
        $source = "public enum Status { Open, Shipped }\npublic sealed record Order(Status Status, string Note) { public Order Shipped() => this with { Status = Status.Shipped }; public Order Sent() => this with { Status = Status.Shipped }; }\npublic static class Handlers\n{\n    {$body}\n}\n";
        $this->assertCount($flagged, new RepeatedNamedCallDetector()->find(Codebase::fromString($source, 'Handlers.cs')), $source);
    }
}
