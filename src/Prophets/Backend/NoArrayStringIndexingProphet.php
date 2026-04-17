<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindArrayStringIndexing;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag array subscript accesses that treat a PHP array like an object.
 *
 * When the key is a known-at-write-time string (literal, class constant,
 * enum case `->value`), the array is a structured record in disguise.
 * Wrap it in a DTO or value object so reads become typed property access.
 */
class NoArrayStringIndexingProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Prefer typed DTOs over string-indexed arrays for structured data';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Array string indexing on structured data is a "PHP array as poor-man's
object" pattern. Every `$row['nodeId']`, `$config['label']`,
`$data['key']`, `$arr[SomeEnum::Case->value]` access is type-unsafe,
IDE-unfriendly, and rename-hostile. Wrap the structure in a typed class
the moment it enters your code.

Bad:
    $nodeId = $row['nodeId'];
    $port   = $row['port'];
    $label  = $config['label'];
    $value  = $data[OrderField::Total->value];

Good:
    // Spatie Data for inbound JSON / config / raw arrays:
    $node = NodeRow::from($row);
    $nodeId = $node->nodeId;

    // Plain readonly value object for code you own:
    final readonly class NodeRow {
        public function __construct(
            public string $nodeId,
            public int $port,
        ) {}
    }

    // Enum-keyed lookups: hide ->value behind a typed matcher:
    OrderField::Total->matches($key);

Dictionary-shaped arrays (dynamic keys, homogeneous values) are fine —
annotate them with `@var array<string, T>` / `@param array<string, T>`
to opt out. Wrapper helpers (`config()`, `Arr::get()`, `data_get()`,
etc.) already signal "this is dynamic lookup" and are not flagged.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(new FindArrayStringIndexing)
            ->sinsFromMatches(
                fn ($match) => sprintf(
                    'Array string indexing on %s[%s] — wrap in a DTO',
                    $match->groups['var'],
                    $match->groups['key'],
                ),
                fn ($match) => $match->groups['source_hint']
                    . '. Use a Spatie Data class for inbound arrays, or a `final readonly` value object for code you own.'
            )
            ->judge();
    }
}
