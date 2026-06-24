<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Doctrine;

use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Runs OptionDiscipline over a self-contained fake project and asserts the COMPLETE
 * set of verdicts equals the manifest exactly — catching both false positives (the
 * `Justified`/`NullRight`/`Exempt` scenarios firing = the old contradiction or an
 * over-fire returning) AND false negatives (an `Adopt`/`NeverNone`/`WrapUnwrap`
 * going silent). Unlike the coarse {@see CorpusTest}, this pins each verdict.
 */
class OptionDisciplineCorpusTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../Fixtures/option-discipline';

    public function test_verdicts_match_the_manifest_exactly(): void
    {
        $actual = $this->actualSignatures();
        $expected = require self::PROJECT . '/expected.php';

        sort($actual);
        sort($expected);

        $this->assertSame(
            $expected,
            $actual,
            "OptionDiscipline verdicts drifted from the fake-project manifest.\n"
            . 'A Justified/NullRight/Exempt signature here means the contradiction or an over-fire is back; '
            . 'a missing Adopt/NeverNone/WrapUnwrap signature means a verdict went silent.',
        );
    }

    /**
     * @return list<string>  "<relative src path>|<case tag>"
     */
    private function actualSignatures(): array
    {
        $src = realpath(self::PROJECT . '/src') ?: self::PROJECT . '/src';
        $files = $this->phpFiles($src);

        $config = ConfigLoader::load(self::PROJECT . '/commandments.php');
        $backend = $config['scrolls']['backend'] ?? [];

        $registry = new ProphetRegistry();
        $registry->registerMany('backend', $backend['prophets'] ?? []);
        $registry->setScrollConfig('backend', $backend);

        $index = CodebaseIndex::build($files);
        $prophets = $registry->getProphets('backend');

        foreach ($prophets as $prophet) {
            if ($prophet instanceof NeedsCodebaseIndex) {
                $prophet->setCodebaseIndex($index);
            }
        }

        $signatures = [];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $rel = ltrim(str_replace($src, '', $file), '/');

            foreach ($prophets as $prophet) {
                $judgment = $prophet->judge($file, $content);

                foreach ([...$judgment->sins, ...$judgment->warnings] as $finding) {
                    $signatures[] = $rel . '|' . $this->caseTag($finding->message);
                }
            }
        }

        return $signatures;
    }

    private function caseTag(string $message): string
    {
        return match (true) {
            str_contains($message, 'decides nothingness') => 'A',
            str_contains($message, 'never empty') => 'B',
            str_contains($message, 'immediately unwrapped') => 'D',
            default => '?',
        };
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
