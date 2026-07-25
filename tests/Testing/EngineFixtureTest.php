<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Testing\BackendFixture;
use PHPUnit\Framework\TestCase;

/**
 * A fixture directory is a project: it declares its detectors' settings in its own
 * `.commandments/config.php`, and the harness tunes them before verifying the markers.
 * That is what lets a rule which is INERT until declared (a namespace-layer map, a
 * threshold) be proven on a self-checking fixture at all.
 */
final class EngineFixtureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-fixture-config-' . uniqid('', true);
        mkdir($this->dir . '/.commandments', 0777, true);

        file_put_contents($this->dir . '/Widget.php', <<<'PHP'
            <?php

            namespace Consumer\App;

            use JesseGall\CodeCommandments\Tests\Testing\DeclaredThresholdDetector;
            use JesseGall\CodeCommandments\Testing\Sinful;

            final class Widget
            {
                #[Sinful(DeclaredThresholdDetector::class)]
                public function wide(string $a, string $b, string $c): void {}

                // righteous twin — under the declared arity, must NOT be flagged.
                public function narrow(string $a): void {}
            }
            PHP);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/Widget.php');
        @unlink($this->dir . '/.commandments/config.php');
        @rmdir($this->dir . '/.commandments');
        @rmdir($this->dir);
    }

    public function test_a_fixture_declares_its_detectors_settings_in_its_own_config(): void
    {
        $this->declare('$config->configure(fn (' . DeclaredThresholdDetector::class . ' $d) => $d->arity(3));');

        $results = new BackendFixture($this->dir, [new DeclaredThresholdDetector])->markerResults();

        $this->assertSame([], $results[0]->missed, 'the declared arity should have made the detector fire on the marked method');
        $this->assertSame([], $results[0]->unexpected);
    }

    public function test_without_a_declaration_the_detector_stays_inert(): void
    {
        // No config file at all: the rule has nothing to enforce, so it finds nothing — which is
        // exactly why the harness needed this machinery before such a rule could be published.
        $results = new BackendFixture($this->dir, [new DeclaredThresholdDetector])->markerResults();

        $this->assertNotSame([], $results[0]->missed, 'an undeclared rule is inert, so the marked sin goes unfound');
    }

    public function test_a_fixture_config_targeting_a_detector_this_fixture_omits_is_silent(): void
    {
        // A fixture legitimately verifies a SUBSET of the catalog — configuring a detector that
        // isn't in this run must not blow the harness up (unlike a project's own config).
        $this->declare('$config->configure(fn (' . DeclaredThresholdDetector::class . ' $d) => $d->arity(3));');

        $this->assertSame([], new BackendFixture($this->dir, [])->markerResults());
    }

    public function test_tune_reports_the_configured_detectors_the_set_did_not_contain(): void
    {
        $config = new Config()->configure(fn (DeclaredThresholdDetector $d) => $d->arity(3));

        $this->assertSame([DeclaredThresholdDetector::class], $config->tune([]));
        $this->assertSame([], $config->tune([new DeclaredThresholdDetector]));
    }

    private function declare(string $body): void
    {
        file_put_contents(
            $this->dir . '/.commandments/config.php',
            "<?php\n\nreturn function (\\JesseGall\\CodeCommandments\\Config \$config): void {\n    {$body}\n};\n",
        );
    }
}

/**
 * A detector with NOTHING to enforce until a project declares it — the shape this machinery
 * exists for. Flags a method whose parameter count reaches the declared arity; with no
 * declaration it never fires.
 */
final class DeclaredThresholdDetector implements Detector
{
    private ?int $arity = null;

    public function arity(int $arity): static
    {
        $this->arity = $arity;

        return $this;
    }

    public function sin(): Sin
    {
        return new GenericException;
    }

    public function find(Codebase $codebase): array
    {
        if ($this->arity === null) {
            return [];
        }

        return $codebase
            ->whereMethodDeclaration()
            ->where(fn (AstNode $node): bool => count($node->valueParamSignature()) >= $this->arity)
            ->get();
    }
}
