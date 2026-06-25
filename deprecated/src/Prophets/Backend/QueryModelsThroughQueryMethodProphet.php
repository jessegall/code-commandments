<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindDirectStaticModelCalls;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use JesseGall\CodeCommandments\Support\Pipes\Php\TypeChecker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Commandment: Query models through ::query() method.
 */
class QueryModelsThroughQueryMethodProphet extends PhpCommandment implements SinRepenter
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
        'withTrashed', 'onlyTrashed',
        'when', 'sole', 'distinct',
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

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $parser = (new ParserFactory)->createForHostVersion();
        $ast = $parser->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $useStatements = $this->extractUseStatements($ast);
        $namespace = $this->extractNamespace($ast);

        $insertPositions = $this->findInsertPositions($ast, $content, $useStatements, $namespace);

        if (empty($insertPositions)) {
            return RepentanceResult::unchanged();
        }

        // Sort by position descending to avoid offset shifts
        usort($insertPositions, fn ($a, $b) => $b['position'] <=> $a['position']);

        $penance = [];

        foreach ($insertPositions as $entry) {
            $content = substr($content, 0, $entry['position'])
                . $entry['insert']
                . substr($content, $entry['position']);

            $penance[] = "Inserted ::query()-> before ::{$entry['method']}()";
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * @return array<array{position: int, method: string, insert: string}>
     */
    private function findInsertPositions(array $ast, string $content, array $useStatements, ?string $namespace): array
    {
        $positions = [];
        $nodeFinder = new NodeFinder;

        /** @var array<Expr\StaticCall> $staticCalls */
        $staticCalls = $nodeFinder->findInstanceOf($ast, Expr\StaticCall::class);

        foreach ($staticCalls as $call) {
            if (! $call->name instanceof Node\Identifier) {
                continue;
            }

            $methodName = $call->name->toString();

            if (! in_array($methodName, self::FORBIDDEN_METHODS, true)) {
                continue;
            }

            if (! $call->class instanceof Node\Name) {
                continue;
            }

            $className = $this->resolveClassName($call->class, $useStatements, $namespace);

            if ($className === null || ! TypeChecker::isModelType($className)) {
                continue;
            }

            $insert = 'query()->';

            // If the chain continues on the next line, format as multi-line
            $afterCall = substr($content, $call->getEndFilePos() + 1);

            if (preg_match('/^\s*\n(\s*)->/', $afterCall, $indentMatch)) {
                $indent = $indentMatch[1];
                $insert = "query()\n{$indent}->";
            }

            $positions[] = [
                'position' => $call->name->getStartFilePos(),
                'method' => $methodName,
                'insert' => $insert,
            ];
        }

        return $positions;
    }

    private function resolveClassName(Node\Name $class, array $useStatements, ?string $namespace): ?string
    {
        $name = $class->toString();

        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $firstPart = $parts[0];

        if (isset($useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $useStatements[$firstPart];
            }

            $parts[0] = $useStatements[$firstPart];

            return implode('\\', $parts);
        }

        if ($namespace) {
            return $namespace . '\\' . $name;
        }

        return $name;
    }

    /**
     * @return array<string, string>
     */
    private function extractUseStatements(array $ast): array
    {
        $uses = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $useUse) {
                            $fqcn = $useUse->name->toString();
                            $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                            $uses[$alias] = $fqcn;
                        }
                    }
                }
            } elseif ($node instanceof Node\Stmt\Use_) {
                foreach ($node->uses as $useUse) {
                    $fqcn = $useUse->name->toString();
                    $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                    $uses[$alias] = $fqcn;
                }
            }
        }

        return $uses;
    }

    private function extractNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                return $node->name?->toString();
            }
        }

        return null;
    }
}
