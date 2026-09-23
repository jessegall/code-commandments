<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Cs\Codebase as CSharpCodebase;
use JesseGall\CodeCommandments\Cs\NodeMatch as CSharpNodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector as CSharpDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Testing\ModuleMarkerVerifier;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\TestCase;

/**
 * A module fixture's markers are the spec: a `@sin Name` comment above a declaration, in the language's
 * own comment syntax, says the rule must flag it. A mark the rule does not flag is a hole; a flag
 * nothing marks is a false positive — the same verdict for Python and for C#.
 */
final class ModuleMarkerVerifierTest extends TestCase
{
    use NeedsTheBridge;

    use TemporaryFolder;

    protected function setUp(): void
    {
        file_put_contents("{$this->root}/shop.py", <<<'PY'
            # @sin Probe
            def marked_and_flagged():
                pass


            def flagged_but_unmarked():
                pass


            class Store:
                # @sin Probe
                limit = 10
            PY);
    }

    public function test_a_hole_and_a_false_positive_are_both_reported(): void
    {
        [$result] = new ModuleMarkerVerifier()->verify(Codebase::scan($this->root), [$this->probe()]);

        $this->assertSame(["{$this->root}/shop.py:12"], $result->missed);
        $this->assertSame(["{$this->root}/shop.py:6"], $result->unexpected);
    }

    public function test_csharp_markers_are_verified_the_same_way(): void
    {
        $this->requireTheBridge();

        file_put_contents("{$this->root}/Store.cs", <<<'CS'
            public class Store
            {
                // @sin Probe
                public void MarkedAndFlagged() {}

                public void FlaggedButUnmarked() {}

                // @sin Probe
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
 * A detector flagging every function — named, so a `# @sin Probe` marker names its sin.
 */
final class PythonProbeDetector implements Detector
{
    public function sin(): Sin
    {
        return new Probe();
    }

    /**
     * @return list<NodeMatch>
     */
    public function find(Codebase $codebase): array
    {
        return $codebase->whereFunction()->get();
    }
}

final class Probe extends Sin
{
    public function __construct()
    {
        parent::__construct(name: 'probe', skill: FixAtTheSource::class, description: 'probe', rule: 'probe');
    }
}

/**
 * The C# twin of {@see PythonProbeDetector}: every function, named by its `// @sin Probe` marker.
 */
final class CSharpProbeDetector implements CSharpDetector
{
    public function sin(): Sin
    {
        return new Probe();
    }

    /**
     * @return list<CSharpNodeMatch>
     */
    public function find(CSharpCodebase $codebase): array
    {
        return $codebase->whereFunction()->get();
    }
}
