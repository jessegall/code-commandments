<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLaravelControllers;
use JesseGall\CodeCommandments\Support\Pipes\Php\MatchPatterns;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: No JSON from controllers - Use Inertia responses only.
 *
 * Exception: API controllers (Http/Controllers/Api/) may return JSON.
 */
class NoJsonResponseProphet extends PhpCommandment
{
    private const PATTERNS = [
        'response_json' => '/response\(\)->json\(/',
        'new_json_response' => '/return\s+new\s+JsonResponse/',
        'response_facade' => '/Response::json\(/',
    ];

    private const MESSAGES = [
        'response_json' => 'JSON response via response()->json()',
        'new_json_response' => 'JSON response via new JsonResponse()',
        'response_facade' => 'JSON response via Response::json()',
    ];

    public function description(): string
    {
        return 'Use Inertia responses instead of JSON in web controllers';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Web controllers should never return JSON responses directly.

Use Inertia responses for all frontend communication. JSON responses
break the Inertia request/response flow and bypass TypeScript type safety.

Exceptions: API controllers (Http/Controllers/Api/) and webhook controllers
may return JSON as they serve different purposes.

Bad:
    return response()->json(['data' => $data]);

Good:
    return Inertia::render('Products/Show', ProductShowPage::from());
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $patterns = (new MatchPatterns)
            ->add('response_json', self::PATTERNS['response_json'])
            ->add('new_json_response', self::PATTERNS['new_json_response'])
            ->add('response_facade', self::PATTERNS['response_facade']);

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelControllers::class)
            ->returnRighteousIfNoClasses()
            ->returnRighteousWhen(fn (PhpContext $ctx) => $ctx->filePathContains('Controllers/Api/'))
            ->returnRighteousWhen(fn (PhpContext $ctx) => $ctx->filePathContains('Controllers\\Api\\'))
            ->returnRighteousWhen(fn (PhpContext $ctx) => $ctx->filePathContains('Webhook'))
            ->pipe($patterns)
            ->sinsFromMatches(
                fn ($match) => self::MESSAGES[$match->name],
                'Use Inertia::render() instead'
            )
            ->judge();
    }
}
