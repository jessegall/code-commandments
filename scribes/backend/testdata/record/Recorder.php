<?php

namespace CodeCommandments\Record;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PHPUnit\Framework\TestCase;

/**
 * Records every PHP source a backend scribe test reads, byte for byte, with what the test's scribe makes of it: the
 * fix, whether it rewrote anything, and how many sins the test's detector finds. The Go scribes are held to these.
 */
final class Recorder
{
    /** @var array<string, array<string, mixed>> */
    public static array $cases = [];

    public static function fromString(string $code, string $path = 'memory.php'): Codebase
    {
        self::record($code, $path);

        return Codebase::fromString($code, $path);
    }

    private static function record(string $code, string $path): void
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            $test = $frame['object'] ?? null;
            if (! $test instanceof TestCase || ! str_starts_with($frame['function'] ?? '', 'test_')) {
                continue;
            }
            [$detector, $scribe] = self::subjects($test);
            $key = $detector::class . "\0" . $code;
            if (isset(self::$cases[$key])) {
                return;
            }
            $codebase = Codebase::fromString($code, $path);
            if ($scribe instanceof NeedsCodebase) {
                $scribe->withCodebase($codebase);
            }
            $findings = $detector->find($codebase);
            $rewrites = $scribe->rewrite($findings);
            self::$cases[$key] = [
                'test' => substr(strrchr($test::class, '\\'), 1) . '::' . $frame['function'],
                'detector' => substr(strrchr($detector::class, '\\'), 1),
                'input' => $code,
                'fixed' => $rewrites === [] ? $code : (string) reset($rewrites),
                'rewrote' => $rewrites !== [],
                'findings' => count($findings),
            ];

            return;
        }
    }

    /**
     * The detector and scribe a test holds: a ScribeTestCase's own, else the detector the test class is named after
     * and the scribe it is fixed by, resolved as the repent step resolves it.
     *
     * @return array{0: object, 1: object}
     */
    private static function subjects(TestCase $test): array
    {
        if (method_exists($test, 'scribe') && method_exists($test, 'detector')) {
            $detector = (fn () => $this->detector())->call($test);
            $scribe = (fn () => $this->scribe())->call($test);

            return [$detector, $scribe];
        }
        $class = str_replace('\\Tests\\', '\\', substr($test::class, 0, -strlen('Test')));
        $detector = new $class();
        $spec = $detector->scribe();
        $scribe = match (true) {
            is_string($spec) => new $spec(),
            $spec instanceof RepentScribe => $spec,
            default => $spec(),
        };

        return [$detector, $scribe];
    }
}
