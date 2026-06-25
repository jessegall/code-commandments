<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Doctrine;

use JesseGall\CodeCommandments\Prophets\Backend\PreferTypedBoundaryProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PHPUnit\Framework\TestCase;

/**
 * The Totality band-0 boundary detector (draft): it flags the `mixed` ROOT on a
 * deserialization boundary, origin-traces to the coercing consumers, and stays
 * silent when the boundary is typed or the value is genuinely heterogeneous.
 */
class BoundaryDetectorTest extends TestCase
{
    private const CORPUS = __DIR__ . '/../Fixtures/corpus';

    public function test_flags_the_mixed_boundary_field_and_traces_its_consumers(): void
    {
        $warnings = $this->detect(self::CORPUS . '/editor-metadata/messy');

        $this->assertCount(1, $warnings, 'exactly the one root, not the downstream symptoms');

        $message = $warnings[0]->message;
        $this->assertStringContainsString('SetNodeMetadataPayload::$value', $message);
        $this->assertStringContainsString('mixed', $message);
        $this->assertStringContainsString('MetadataHandler', $message, 'the origin trace names a coercing consumer');
    }

    public function test_silent_on_the_typed_golden(): void
    {
        $this->assertSame([], $this->detect(self::CORPUS . '/editor-metadata/golden'));
    }

    public function test_silent_without_a_deserialization_boundary(): void
    {
        // subscriptions/messy is plenty smelly, but has no mixed Data/FormRequest field —
        // the boundary detector must not fire on it.
        $this->assertSame([], $this->detect(self::CORPUS . '/subscriptions/messy'));
    }

    /**
     * @return list<Warning>
     */
    private function detect(string $dir): array
    {
        $files = array_values(array_filter((array) glob($dir . '/*.php')));
        $index = CodebaseIndex::build($files);

        $prophet = new PreferTypedBoundaryProphet();
        $prophet->setCodebaseIndex($index);

        $warnings = [];

        foreach ($files as $file) {
            $warnings = [...$warnings, ...$prophet->judge($file, (string) file_get_contents($file))->warnings];
        }

        return $warnings;
    }
}
