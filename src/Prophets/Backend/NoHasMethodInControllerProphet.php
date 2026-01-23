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
 * Commandment: No has()/hasFile()/filled()/boolean() in controllers - Use typed FormRequest getters.
 */
class NoHasMethodInControllerProphet extends PhpCommandment
{
    private const PATTERNS = [
        'has' => ['pattern' => '/\$request->has\s*\(/', 'message' => 'Using $request->has() in controller', 'suggestion' => 'Use typed FormRequest getters with nullable returns'],
        'hasFile' => ['pattern' => '/\$request->hasFile\s*\(/', 'message' => 'Using $request->hasFile() in controller', 'suggestion' => 'Use typed FormRequest getter that returns ?UploadedFile'],
        'filled' => ['pattern' => '/\$request->filled\s*\(/', 'message' => 'Using $request->filled() in controller', 'suggestion' => 'Use typed FormRequest getters with nullable returns'],
        'boolean' => ['pattern' => '/\$request->boolean\s*\(/', 'message' => 'Using $request->boolean() in controller', 'suggestion' => 'Use typed FormRequest getter that returns bool'],
    ];

    public function description(): string
    {
        return 'Use typed FormRequest getters instead of has()/filled()/boolean()';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use has(), hasFile(), filled(), or boolean() methods in controllers.

These methods should be encapsulated in FormRequest typed getters.
Use the pattern: if ($value = $request->getValue()) { ... }

Bad:
    if ($request->has('name')) {
        $name = $request->input('name');
    }

Good:
    // In FormRequest:
    public function getName(): ?string {
        return $this->input('name');
    }

    // In Controller:
    if ($name = $request->getName()) {
        // use $name
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $patterns = new MatchPatterns;
        foreach (self::PATTERNS as $name => $config) {
            $patterns->add($name, $config['pattern']);
        }

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelControllers::class)
            ->returnRighteousIfNoClasses()
            ->pipe($patterns)
            ->sinsFromMatches(
                fn ($match) => self::PATTERNS[$match->name]['message'],
                fn ($match) => self::PATTERNS[$match->name]['suggestion']
            )
            ->judge();
    }
}
