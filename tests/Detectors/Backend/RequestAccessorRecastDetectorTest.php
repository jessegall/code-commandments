<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\RequestAccessorRecastDetector;
use PHPUnit\Framework\TestCase;

final class RequestAccessorRecastDetectorTest extends TestCase
{
    public function test_flags_call_site_recasts_in_both_forms(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Http { class Request {} }
        namespace App {
            use Illuminate\Http\Request;

            final class Handler {
                public function viaToString(Request $request): string {
                    return $request->string('name')->toString();
                }
                public function viaCast(Request $request): string {
                    return (string) $request->string('id');
                }
            }
        }
        PHP;

        $hits = (new RequestAccessorRecastDetector)->find(Codebase::fromString($code));

        $this->assertSame(
            ['App\\Handler::viaToString', 'App\\Handler::viaCast'],
            array_map(static fn ($m): string => $m->scope(), $hits),
        );
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Http { class Request {} }
        namespace App {
            use Illuminate\Http\Request;

            // the named accessor INSIDE the request class — the proper home, exempt
            final class CreateRequest extends Request {
                public function name(): string { return $this->string('name')->toString(); }
            }
            // typed accessor passed along, NOT re-coerced — fine
            final class Passer {
                public function run(Request $request) { return $request->string('x'); }
            }
            // not a request at all — a value object with its own string() method
            final class Money {
                public function string(string $k): string { return $k; }
            }
            final class Formatter {
                public function show(Money $money): string { return $money->string('eur')->toString(); }
            }
        }
        PHP;

        $this->assertSame([], (new RequestAccessorRecastDetector)->find(Codebase::fromString($code)));
    }
}
