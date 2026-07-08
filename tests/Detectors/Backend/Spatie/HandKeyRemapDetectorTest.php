<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\HandKeyRemapDetector;
use PHPUnit\Framework\TestCase;

/**
 * `SomeData::from(['recordCompany' => $src['record_company'], …])` is a hand-written snake→camel remap a
 * class-level `#[MapInputName]` owns. A transformed value, a mixed source, an identity mapping, or a
 * non-mechanical key is spared.
 */
final class HandKeyRemapDetectorTest extends TestCase
{
    private function hits(string $php): int
    {
        return count(new HandKeyRemapDetector()->find(Codebase::fromString($php)));
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class ContractData extends Data {
            public function __construct(
                public readonly string $recordCompany,
                public readonly string $title,
            ) {}
        }
        PHP;

    private function code(string $body): string
    {
        return self::HEADER . "\n" . $body;
    }

    public function test_flags_a_mechanical_snake_to_camel_remap(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Importer {
                public function build(array $src): ContractData {
                    return ContractData::from([
                        'recordCompany' => $src['record_company'],
                        'title' => $src['title'],
                    ]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_transformed_value(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Importer {
                public function build(array $src): ContractData {
                    return ContractData::from([
                        'recordCompany' => strtoupper($src['record_company']),
                        'title' => $src['title'],
                    ]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_mixed_source(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Importer {
                public function build(array $src, array $meta): ContractData {
                    return ContractData::from([
                        'recordCompany' => $src['record_company'],
                        'title' => $meta['title'],
                    ]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_an_identity_mapping(): void
    {
        // Keys already match their fetch — no rename, so no mapper would change anything.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Importer {
                public function build(array $src): ContractData {
                    return ContractData::from([
                        'title' => $src['title'],
                        'recordCompany' => $src['recordCompany'],
                    ]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_non_mechanical_key(): void
    {
        // 'recordCompany' snake is 'record_company', not 'label' — not the mapper's transform.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Importer {
                public function build(array $src): ContractData {
                    return ContractData::from([
                        'recordCompany' => $src['label'],
                        'title' => $src['title'],
                    ]);
                }
            }
            PHP)));
    }
}
