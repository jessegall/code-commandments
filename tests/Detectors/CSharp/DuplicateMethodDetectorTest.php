<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Detectors\CSharp\DuplicateMethodDetector;
use PHPUnit\Framework\TestCase;

/**
 * A C# body written twice is found whatever each copy is called and however it is formatted; a body too
 * small to share, a lone method, and two classes' constructors setting their own state are not copies.
 */
final class DuplicateMethodDetectorTest extends TestCase
{
    private const string LOAD = <<<'CS'
        {
            var found = new List<int>();
            foreach (var id in ids)
            {
                if (id > limit) { break; }
                found.Add(id * 2);
            }
            return found;
        }
        CS;

    protected function setUp(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }
    }

    public function test_flags_a_copy_renamed_under_another_method(): void
    {
        $found = $this->findIn("public class Orders\n{\n    public List<int> Load(List<int> ids, int limit)\n" . self::LOAD . "\n\n    public List<int> FetchIds(List<int> ids, int limit)\n" . self::LOAD . "\n}\n");

        $this->assertSame(['MethodDeclaration Load', 'MethodDeclaration FetchIds'], $found);
    }

    public function test_formatting_and_comments_do_not_hide_a_copy(): void
    {
        $reformatted = "{\n    // collect the doubled ids\n    var found = new List<int>(); foreach (var id in ids) { if (id > limit) break; found.Add(id\n        * 2); }\n    return found;\n}";

        $found = $this->findIn("public class Orders\n{\n    public List<int> Load(List<int> ids, int limit)\n" . self::LOAD . "\n\n    public List<int> Again(List<int> ids, int limit)\n{$reformatted}\n}\n");

        $this->assertSame(['MethodDeclaration Load', 'MethodDeclaration Again'], $found);
    }

    public function test_ignores_bodies_below_the_floor_and_a_single_method(): void
    {
        $this->assertSame([], $this->findIn("public class A\n{\n    public int X(int y) { return y + 1; }\n    public int Z(int y) { return y + 1; }\n}\n"));
        $this->assertSame([], $this->findIn("public class Orders\n{\n    public List<int> Load(List<int> ids, int limit)\n" . self::LOAD . "\n}\n"));
    }

    public function test_ignores_two_classes_setting_their_own_state_alike(): void
    {
        $constructor = "(int a, int b)\n    {\n        if (a < 0 || b < 0) { throw new System.ArgumentException(\"negative\"); }\n        this.a = a * 2;\n        this.b = b * 2;\n    }\n    private readonly int a;\n    private readonly int b;";

        $this->assertSame([], $this->findIn("public class A\n{\n    public A{$constructor}\n}\n\npublic class B\n{\n    public B{$constructor}\n}\n"));
    }

    /**
     * @return list<string>
     */
    private function findIn(string $source): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new DuplicateMethodDetector()->find(Codebase::fromString("using System.Collections.Generic;\n\n{$source}", 'Orders.cs')));
    }
}
