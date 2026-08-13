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

    public function test_a_round_trip_of_our_own_value_is_not_a_boundary_decode(): void
    {
        // #462: encoding OUR value and decoding it right back is how you obtain its serialized form
        // as data to walk. Nothing crossed a boundary — there is no untyped input here to name, and
        // the shape being walked is deliberately "whatever this serializes to". A cast in between
        // changes nothing; a decode of anything else is a boundary decode as before.
        $code = <<<'PHP'
        <?php
        class S
        {
            public function composed(Sequence $sequence): array
            {
                return json_decode((string) json_encode($sequence->body(), JSON_PRESERVE_ZERO_FRACTION), true);
            }

            public function plain(Sequence $sequence): array
            {
                return json_decode(json_encode($sequence), true);
            }

            public function fromTheWire(string $body): array
            {
                return json_decode($body, true);
            }
        }
        PHP;

        $scopes = array_map(
            static fn ($m): string => $m->scope(),
            (new RawDecodedArrayReturnDetector)->find(Codebase::fromString($code)),
        );

        $this->assertSame(['S::fromTheWire'], $scopes);
    }
}
