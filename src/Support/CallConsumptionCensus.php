<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\CallGraph\CallSite;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Cross-file answer to "does EVERY caller of this method reject its absence?"
 *
 * For a method that returns a nullable/Option, it walks every resolvable call
 * site (via the {@see CodebaseIndex}), re-parses each caller file once, and
 * classifies the consumption with {@see CallConsumptionClassifier}. A caller
 * that merely `return`s the result (a thin wrapper) is followed transitively into
 * ITS callers — so a `find()` consumed two hops up via `?? throw` still reads as
 * an invariant.
 *
 * `allCallersDeNull()` is deliberately conservative: it is true only when at
 * least one caller is resolved and EVERY resolved call site (transitively)
 * de-nulls — any handled, unknown, or unresolvable site, a cycle, exceeding the
 * depth/caller/file caps, or zero visible callers all yield false. The invisible
 * caller must never manufacture a false "everyone de-nulls".
 */
final class CallConsumptionCensus
{
    private const MAX_DEPTH = 3;

    private const MAX_CALLERS = 40;

    private const MAX_FILES = 60;

    private \PhpParser\Parser $parser;

    private CallConsumptionClassifier $classifier;

    /** @var array<string, array{resolvedCallers: int, allDeNull: bool, anyHandles: bool}> */
    private array $resultMemo = [];

    /** @var array<string, array{parents: array<int, Node>, byPos: array<int, Expr>, byLine: array<int, list<Expr>>}|null> */
    private array $fileCache = [];

    private int $filesParsed = 0;

    public function __construct(
        private readonly CodebaseIndex $index,
    ) {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->classifier = new CallConsumptionClassifier;
    }

    /**
     * @param  'nullable'|'option'  $kind
     */
    public function allCallersDeNull(string $calleeFqcn, string $method, string $kind = 'nullable'): bool
    {
        return $this->walk(ltrim($calleeFqcn, '\\'), $method, $kind, 0, [])['allDeNull'];
    }

    /**
     * @param  'nullable'|'option'  $kind
     * @return array{resolvedCallers: int, allDeNull: bool, anyHandles: bool}
     */
    public function consumption(string $calleeFqcn, string $method, string $kind = 'nullable'): array
    {
        return $this->walk(ltrim($calleeFqcn, '\\'), $method, $kind, 0, []);
    }

    /**
     * @param  'nullable'|'option'  $kind
     * @param  array<string, true>  $visited
     * @return array{resolvedCallers: int, allDeNull: bool, anyHandles: bool}
     */
    private function walk(string $fqcn, string $method, string $kind, int $depth, array $visited): array
    {
        $neutral = ['resolvedCallers' => 0, 'allDeNull' => false, 'anyHandles' => false];

        if ($depth > self::MAX_DEPTH) {
            return $neutral;
        }

        $vkey = $fqcn . '::' . $method;

        if (isset($visited[$vkey])) {
            return $neutral; // cycle — can't prove
        }

        $memoKey = $vkey . '::' . $kind;

        if ($depth === 0 && isset($this->resultMemo[$memoKey])) {
            return $this->resultMemo[$memoKey];
        }

        $visited[$vkey] = true;

        $callSites = $this->index->callersOf($fqcn, $method);
        $total = count($callSites);

        if ($total === 0) {
            return $this->remember($memoKey, $depth, $neutral);
        }

        $capExceeded = $total > self::MAX_CALLERS;
        $callSites = array_slice($callSites, 0, self::MAX_CALLERS);

        $resolved = 0;
        $allDeNull = ! $capExceeded;
        $anyHandles = false;

        foreach ($callSites as $callSite) {
            $verdict = $this->classifyCallSite($callSite, $kind);

            if ($verdict === CallConsumptionClassifier::PASSTHROUGH) {
                $sub = $this->walk($callSite->callerClassFqcn, $callSite->callerMethod, $kind, $depth + 1, $visited);
                $anyHandles = $anyHandles || $sub['anyHandles'];

                if ($sub['resolvedCallers'] >= 1 && $sub['allDeNull']) {
                    $resolved++;
                } else {
                    $allDeNull = false;
                }
            } elseif ($verdict === CallConsumptionClassifier::DENULL) {
                $resolved++;
            } elseif ($verdict === CallConsumptionClassifier::HANDLES) {
                $resolved++;
                $anyHandles = true;
                $allDeNull = false;
            } else { // UNKNOWN
                $allDeNull = false;
            }
        }

        $result = [
            'resolvedCallers' => $resolved,
            'allDeNull' => $allDeNull && $resolved === $total && $resolved >= 1,
            'anyHandles' => $anyHandles,
        ];

        return $this->remember($memoKey, $depth, $result);
    }

    /**
     * @param  array{resolvedCallers: int, allDeNull: bool, anyHandles: bool}  $result
     * @return array{resolvedCallers: int, allDeNull: bool, anyHandles: bool}
     */
    private function remember(string $memoKey, int $depth, array $result): array
    {
        if ($depth === 0) {
            $this->resultMemo[$memoKey] = $result;
        }

        return $result;
    }

    /**
     * @param  'nullable'|'option'  $kind
     */
    private function classifyCallSite(CallSite $callSite, string $kind): string
    {
        $file = $this->fileNodes($callSite->callerFile);

        if ($file === null) {
            return CallConsumptionClassifier::UNKNOWN;
        }

        $node = null;

        if ($callSite->startFilePos >= 0 && isset($file['byPos'][$callSite->startFilePos])) {
            $node = $file['byPos'][$callSite->startFilePos];
        } else {
            $sameLine = collect($file['byLine'][$callSite->line] ?? [])
                ->filter(static fn (Expr $n): bool => self::callName($n) === $callSite->calleeMethod)
                ->values()
                ->all();

            if (count($sameLine) === 1) {
                $node = $sameLine[0];
            }
        }

        if ($node === null) {
            return CallConsumptionClassifier::UNKNOWN;
        }

        $parent = $file['parents'][spl_object_id($node)] ?? null;

        return $this->classifier->classify($node, $parent, $kind);
    }

    /**
     * Parse a caller file once: parent map + call nodes indexed by byte offset
     * and by line. Returns null on parse failure or once the file cap is hit.
     *
     * @return array{parents: array<int, Node>, byPos: array<int, Expr>, byLine: array<int, list<Expr>>}|null
     */
    private function fileNodes(string $file): ?array
    {
        if (array_key_exists($file, $this->fileCache)) {
            return $this->fileCache[$file];
        }

        if ($this->filesParsed >= self::MAX_FILES) {
            return $this->fileCache[$file] = null;
        }

        $content = @file_get_contents($file);

        if ($content === false) {
            return $this->fileCache[$file] = null;
        }

        try {
            $ast = $this->parser->parse($content);
        } catch (Throwable) {
            $ast = null;
        }

        if ($ast === null) {
            return $this->fileCache[$file] = null;
        }

        $this->filesParsed++;

        $parents = [];
        $byPos = [];
        $byLine = [];
        $this->index_($ast, null, $parents, $byPos, $byLine);

        return $this->fileCache[$file] = ['parents' => $parents, 'byPos' => $byPos, 'byLine' => $byLine];
    }

    /**
     * @param  array<Node>  $nodes
     * @param  array<int, Node>  $parents
     * @param  array<int, Expr>  $byPos
     * @param  array<int, list<Expr>>  $byLine
     */
    private function index_(array $nodes, ?Node $parent, array &$parents, array &$byPos, array &$byLine): void
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node) {
                continue;
            }

            if ($parent !== null) {
                $parents[spl_object_id($node)] = $parent;
            }

            if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall) {
                $pos = $node->getStartFilePos();

                if ($pos >= 0) {
                    $byPos[$pos] = $node;
                }

                $byLine[$node->getStartLine()][] = $node;
            }

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};
                $this->index_(is_array($child) ? $child : [$child], $node, $parents, $byPos, $byLine);
            }
        }
    }

    private static function callName(Expr $node): ?string
    {
        if (($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall)
            && $node->name instanceof Node\Identifier
        ) {
            return $node->name->toString();
        }

        return null;
    }
}
