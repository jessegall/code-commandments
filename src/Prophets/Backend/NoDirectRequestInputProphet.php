<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindDirectRequestMethodCalls;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: No direct request data access in controllers - Use typed FormRequest getters.
 */
class NoDirectRequestInputProphet extends PhpCommandment
{
    private const METHODS = [
        'has' => ['message' => 'Using has() on request in controller', 'suggestion' => 'Use typed FormRequest getter with nullable return'],
        'hasFile' => ['message' => 'Using hasFile() on request in controller', 'suggestion' => 'Use typed FormRequest getter that returns ?UploadedFile'],
        'filled' => ['message' => 'Using filled() on request in controller', 'suggestion' => 'Use typed FormRequest getter with nullable return'],
        'boolean' => ['message' => 'Using boolean() on request in controller', 'suggestion' => 'Use typed FormRequest getter that returns bool'],
        'input' => ['message' => 'Using input() on request in controller', 'suggestion' => 'Use typed FormRequest getter method instead'],
    ];

    public function description(): string
    {
        return 'Use typed FormRequest getters instead of direct request data access';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never access request data directly in controllers using methods like
has(), hasFile(), filled(), boolean(), or input().

These methods should be encapsulated in FormRequest typed getters.
This applies to both $request-> and $this->request-> usage.

The prophet uses AST analysis and reflection to verify that the object
being called on is actually a Laravel request class.

Bad:
    if ($request->has('name')) {
        $name = $request->input('name');
    }

    $window = $this->request->input('movementWindow', '30d');

Good:
    // In FormRequest:
    public function getName(): ?string {
        return $this->input('name');
    }

    public function getMovementWindow(): string {
        $window = $this->input('movementWindow', '30d');

        if (! in_array($window, ['7d', '30d', '90d'])) {
            return '30d';
        }

        return $window;
    }

    // In Controller:
    if ($name = $request->getName()) {
        // use $name
    }

    $window = $request->getMovementWindow();
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->onlyControllers()
            ->pipe(ExtractUseStatements::class)
            ->pipe(new FindDirectRequestMethodCalls(array_keys(self::METHODS)))
            ->sinsFromMatches(
                fn ($match) => self::METHODS[$match->name]['message'],
                fn ($match) => self::METHODS[$match->name]['suggestion']
            )
            ->judge();
    }
}
