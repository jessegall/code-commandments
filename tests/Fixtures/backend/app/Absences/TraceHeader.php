<?php

namespace Shop\Absences;

use Illuminate\Http\Request;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads the trace a request carries, so a log line can be matched to the request that wrote it.
 */
final class TraceHeader
{
    private const string HEADER = 'X-Shop-Trace';

    #[Sinful(CoalescedPastDefault::class)]
    public function traceOf(Request $request): string
    {
        return $request->header(self::HEADER) ?? 'untraced';
    }

    #[Fixed(CoalescedPastDefault::class)]
    public function traceOrUntraced(Request $request): string
    {
        return $request->header(self::HEADER, 'untraced');
    }

    #[Righteous(CoalescedPastDefault::class)]
    public function requiredTrace(Request $request): string
    {
        return $request->header(self::HEADER) ?? throw new MissingTrace(self::HEADER);
    }
}
