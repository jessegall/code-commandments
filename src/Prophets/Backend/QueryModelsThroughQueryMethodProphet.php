<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindDirectStaticModelCalls;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: Query models through ::query() method.
 */
class QueryModelsThroughQueryMethodProphet extends PhpCommandment
{
    private const FORBIDDEN_METHODS = [
        'where', 'whereIn', 'whereNotIn', 'whereNull', 'whereNotNull',
        'whereHas', 'whereDoesntHave', 'whereBetween', 'whereDate',
        'with', 'without', 'withCount', 'withWhereHas',
        'select', 'selectRaw', 'addSelect',
        'orderBy', 'orderByDesc', 'latest', 'oldest',
        'groupBy', 'having', 'limit', 'offset', 'skip', 'take',
        'first', 'firstOrFail', 'firstWhere', 'firstOrNew', 'firstOrCreate',
        'get', 'paginate', 'simplePaginate', 'cursorPaginate',
        'join', 'leftJoin', 'rightJoin',
        'pluck', 'value', 'exists', 'doesntExist',
        'chunk', 'each', 'cursor', 'lazy',
        'withTrashed', 'onlyTrashed',
        'upsert', 'when', 'sole', 'distinct',
    ];

    public function description(): string
    {
        return 'Query models through the ::query() method instead of direct static calls';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always use Model::query() as the entry point for Eloquent query builder
chains instead of calling query builder methods directly as static calls.

Direct static calls like User::where(...) bypass the explicit query()
entry point, making it less clear that a query builder chain is being
started. Using ::query() makes the intent explicit and consistent.

Methods like find(), findOrFail(), count(), all(), create(), delete(),
update(), updateOrCreate(), factory(), and observe() are allowed as
direct static calls since they are not query builder entry points.

Bad:
    $users = User::where('active', true)->get();
    $stockpiles = Stockpile::with('items')->get();
    $first = User::firstWhere('email', $email);

Good:
    $users = User::query()->where('active', true)->get();
    $stockpiles = Stockpile::query()->with('items')->get();
    $first = User::query()->firstWhere('email', $email);

    // These are allowed (not query builder entry points):
    $user = User::find(1);
    $user = User::findOrFail(1);
    $count = User::count();
    $all = User::all();
    User::create([...]);
    User::factory()->create();
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(new FindDirectStaticModelCalls(self::FORBIDDEN_METHODS))
            ->sinsFromMatches(
                fn ($match) => "Using {$match->name}() directly on model instead of ::query()->{$match->name}()",
                fn () => 'Use Model::query()->method() instead of Model::method()'
            )
            ->judge();
    }
}
