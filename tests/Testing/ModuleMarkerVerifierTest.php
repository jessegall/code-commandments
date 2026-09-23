<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\CSharp\Detector as CSharpDetector;
use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase as CSharpCodebase;
use JesseGall\CodeCommandments\Cs\NodeMatch as CSharpNodeMatch;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Testing\ModuleMarkerVerifier;
use PHPUnit\Framework\TestCase;

/**
 * A module fixture's markers are the spec: a `@sin Name` comment above a declaration, in the language's
 * own comment syntax, says the rule must flag it. A mark the rule does not flag is a hole; a flag
 * nothing marks is a false positive — the same verdict for Python and for C#.
 */
final class ModuleMarkerVerifierTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-py-markers-' . uniqid();
        mkdir($this->root);
        file_put_contents("{$this->root}/shop.py", <<<'PY'
            # @sin PythonProbe
            def marked_and_flagged():
                pass


            def flagged_but_unmarked():
                pass


            class Store:
                # @sin PythonProbe
                limit = 10
            PY);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_hole_and_a_false_positive_are_both_reported(): void
    {
        [$result] = new ModuleMarkerVerifier()->verify(Codebase::scan($this->root), [$this->probe()]);

        $this->assertSame(["{$this->root}/shop.py:12"], $result->missed);
        $this->assertSame(["{$this->root}/shop.py:6"], $result->unexpected);
    }

    public function test_csharp_markers_are_verified_the_same_way(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }

        file_put_contents("{$this->root}/Store.cs", <<<'CS'
            public class Store
            {
                // @sin CSharpProbe
                public void MarkedAndFlagged() {}

                public void FlaggedButUnmarked() {}

                // @sin CSharpProbe
                private int limit = 10;
            }
            CS);

        [$result] = new ModuleMarkerVerifier()->verify(CSharpCodebase::scan($this->root), [new CSharpProbeDetector()]);

        $this->assertSame(["{$this->root}/Store.cs:9"], $result->missed);
        $this->assertSame(["{$this->root}/Store.cs:6"], $result->unexpected);
    }

    private function probe(): Detector
    {
        return new PythonProbeDetector();
    }
}

/**
 * A detector flagging every function — named, so a `# @sin PythonProbe` marker names its sin.
 */
final class PythonProbeDetector implements Detector
{
    public function sin(): Sin
    {
        return new PythonProbe();
    }

    /**
     * @return list<NodeMatch>
     */
    public function find(Codebase $codebase): array
    {
        return $codebase->whereFunction()->get();
    }
}

final class PythonProbe extends Sin
{
    public function __construct()
    {
        parent::__construct(name: 'probe', skill: FixAtTheSource::class, description: 'probe', rule: 'probe');
    }
}

/**
 * The C# twin of {@see PythonProbeDetector}: every function, named by its `// @sin CSharpProbe` marker.
 */
final class CSharpProbeDetector implements CSharpDetector
{
    public function sin(): Sin
    {
        return new CSharpProbe();
    }

    /**
     * @return list<CSharpNodeMatch>
     */
    public function find(CSharpCodebase $codebase): array
    {
        return $codebase->whereFunction()->get();
    }
}

final class CSharpProbe extends Sin
{
    public function __construct()
    {
        parent::__construct(name: 'probe', skill: FixAtTheSource::class, description: 'probe', rule: 'probe');
    }
}
