<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RawDecodedArrayReturnDetector;
use PHPUnit\Framework\TestCase;

final class RawDecodedArrayReturnDetectorTest extends TestCase
{
    public function test_flags_a_directly_returned_decoded_array_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S
        {
            public function raw(string $body): array
            {
                return json_decode($body, true);
            }

            public function wrapped(string $body): TrackingStatus
            {
                return TrackingStatus::from(json_decode($body, true));
            }

            public function local(string $body): array
            {
                $data = json_decode($body, true);

                return $data;
            }
        }
        PHP;

        $hits = (new RawDecodedArrayReturnDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::raw'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
