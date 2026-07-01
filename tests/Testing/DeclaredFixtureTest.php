<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use InvalidArgumentException;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Testing\DeclaredFixture;
use JesseGall\CodeCommandments\Testing\HasFixture;
use PHPUnit\Framework\TestCase;

/**
 * The consumer path: a custom detector that declares its own fixture directory is
 * verified against the markers in that directory, exactly like the package's own
 * detectors are against the Shop app.
 */
final class DeclaredFixtureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-fixture-' . uniqid('', true);
        mkdir($this->dir, 0777, true);

        file_put_contents($this->dir . '/Widget.php', <<<'PHP'
            <?php

            namespace Consumer\App;

            use JesseGall\CodeCommandments\Tests\Testing\BareThrowDetector;
            use JesseGall\CodeCommandments\Testing\Sinful;

            final class Widget
            {
                #[Sinful(BareThrowDetector::class)]
                public function boom(): void
                {
                    throw new \Exception('nope');
                }

                // righteous twin — a typed exception, must NOT be flagged.
                public function safe(): void
                {
                    throw new WidgetException('typed');
                }
            }
            PHP);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/Widget.php');
        @rmdir($this->dir);
    }

    public function test_a_custom_detector_is_verified_against_its_declared_fixture(): void
    {
        $results = new DeclaredFixture([new BareThrowDetector($this->dir)])->markerResults();

        $this->assertCount(1, $results);
        $this->assertSame([], $results[0]->missed, 'the marked throw was flagged');
        $this->assertSame([], $results[0]->unexpected, 'the typed-exception twin was not flagged');
    }

    public function test_a_detector_without_a_declared_fixture_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeclaredFixture([new FixturelessDetector()])->markerResults();
    }
}

/** A tiny custom backend detector that carries its own fixture — flags `throw new \Exception`. */
final class BareThrowDetector implements Detector, HasFixture
{
    public function __construct(private readonly string $fixture) {}

    public function fixturePath(): string
    {
        return $this->fixture;
    }

    public function sin(): Sin
    {
        return new GenericException();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $n): bool => $n->newClassName() === 'Exception')
            ->where(static fn (AstNode $n): bool => $n->parent()->isThrow())
            ->get();
    }
}

/** A custom detector that forgot to declare a fixture — {@see DeclaredFixture} must refuse it. */
final class FixturelessDetector implements Detector
{
    public function sin(): Sin
    {
        return new GenericException();
    }

    public function find(Codebase $codebase): array
    {
        return [];
    }
}
