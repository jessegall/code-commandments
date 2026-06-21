<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * A class's STATE — its constants and properties — should be declared at the
 * top of the body, before any method. When a `const` or property is wedged
 * between methods you have to scroll past behavior to discover what state the
 * class even holds; declarations scattered through the file read as an
 * afterthought rather than the shape of the type.
 *
 * The rule is purely about ORDER, derived from the AST: walk the class body in
 * source order and the moment a constant or property appears AFTER a method has
 * been declared, it is out of place.
 *
 * Auto-fixable: `repent` hoists every misplaced constant/property (with its
 * docblock and attributes) to just above the first method, in their original
 * relative order — a behavior-preserving move that touches no values, types, or
 * visibility.
 */
#[IntroducedIn('2.27.0')]
class ConstantsAndPropertiesFirstProphet extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Declare all constants and properties at the top of the class, before any methods';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('a class constant or property is declared after a method — state is scattered through the behavior instead of sitting together at the top.')
            ->leaveWhen('never, really — the reorder is behavior-preserving. The only case is a non-class construct the rule does not touch.')
            ->whenUnsure('run `repent` — it only moves declarations above the first method, in their original order, and never changes a value, type, or visibility.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A class reads top-down: its state should be declared before its behavior so a
reader sees WHAT the type holds before HOW it acts. Constants and properties
wedged between methods force you to scan the whole body to reconstruct the
class's shape.

Bad — constants declared in the middle of the body, between methods:
    class Provider
    {
        public function register(): void { /* ... */ }

        private const array COMMANDS = [/* ... */];   // <- after a method

        private function registerCommands(): void
        {
            $this->commands(self::COMMANDS);
        }

        private const array EMITTERS = [/* ... */];   // <- after a method
    }

Good — all state first, then behavior:
    class Provider
    {
        private const array COMMANDS = [/* ... */];
        private const array EMITTERS = [/* ... */];

        public function register(): void { /* ... */ }

        private function registerCommands(): void
        {
            $this->commands(self::COMMANDS);
        }
    }

WHAT FIRES — a class/trait/enum constant or property declaration that appears
after any method declaration in the same class body.

WHAT DOES NOT — constructor-promoted properties (they are parameters, not body
declarations); `use Trait;` statements and enum cases; anything in an interface;
declarations that are already above every method.

Auto-fixable: `repent` lifts each misplaced declaration (with its docblock and
attributes) to just above the first method, preserving relative order.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ($this->collect($ast, $content) as $group) {
            foreach ($group['members'] as $member) {
                $warnings[] = $this->warningAt($member['line'], $member['message'], $member['snippet'], $member['symbol'], true);
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    public function canRepent(string $filePath): bool
    {
        return str_ends_with($filePath, '.php');
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $operations = [];
        $penance = [];

        foreach ($this->collect($ast, $content) as $group) {
            $block = '';

            foreach ($group['members'] as $member) {
                $block .= $member['text'];
                $operations[] = ['key' => $member['start'], 'kind' => 'remove', 'start' => $member['start'], 'end' => $member['end']];
                $penance[] = $member['penance'];
            }

            $operations[] = ['key' => $group['anchor'], 'kind' => 'insert', 'pos' => $group['anchor'], 'text' => $block];
        }

        if ($operations === []) {
            return RepentanceResult::unchanged();
        }

        // Apply from the bottom of the file up so earlier offsets stay valid: a
        // class's insertion anchor always sits below the file region it edits,
        // and every removal sits above the anchor, so no operation disturbs an
        // offset still to be applied.
        usort($operations, static fn (array $a, array $b): int => $b['key'] <=> $a['key']);

        foreach ($operations as $op) {
            if ($op['kind'] === 'remove') {
                $content = substr($content, 0, $op['start']) . substr($content, $op['end']);
            } else {
                $content = substr($content, 0, $op['pos']) . $op['text'] . substr($content, $op['pos']);
            }
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * Group every misplaced constant/property by the class it lives in, carrying
     * the insertion anchor (above the first method) and the move span of each.
     *
     * @param  array<Node>  $ast
     * @return list<array{anchor: int, members: list<array{start: int, end: int, text: string, line: int, message: string, snippet: string, symbol: string, penance: string}>}>
     */
    private function collect(array $ast, string $content): array
    {
        $groups = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $class) {
            if (! $class instanceof Node\Stmt\Class_ && ! $class instanceof Node\Stmt\Trait_ && ! $class instanceof Node\Stmt\Enum_) {
                continue;
            }

            $firstMethod = null;

            foreach ($class->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\ClassMethod) {
                    $firstMethod = $stmt;
                    break;
                }
            }

            if ($firstMethod === null) {
                continue;
            }

            $members = [];

            foreach ($class->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\ClassConst && ! $stmt instanceof Node\Stmt\Property) {
                    continue;
                }

                if ($stmt->getStartFilePos() < $firstMethod->getStartFilePos()) {
                    continue;
                }

                $members[] = $this->memberRecord($stmt, $content);
            }

            if ($members === []) {
                continue;
            }

            $groups[] = [
                'anchor' => $this->lineStart($content, $this->nodeStart($firstMethod)),
                'members' => $members,
            ];
        }

        return $groups;
    }

    /**
     * @return array{start: int, end: int, text: string, line: int, message: string, snippet: string, symbol: string, penance: string}
     */
    private function memberRecord(Node\Stmt\ClassConst|Node\Stmt\Property $stmt, string $content): array
    {
        $start = $this->lineStart($content, $this->nodeStart($stmt));
        $end = $this->spanEnd($content, $stmt->getEndFilePos());

        [$kind, $name, $label] = $stmt instanceof Node\Stmt\ClassConst
            ? ['const', $stmt->consts[0]->name->toString(), 'Constant `' . $stmt->consts[0]->name->toString() . '`']
            : ['prop', $stmt->props[0]->name->toString(), 'Property `$' . $stmt->props[0]->name->toString() . '`'];

        $line = $stmt->getStartLine();

        return [
            'start' => $start,
            'end' => $end,
            'text' => substr($content, $start, $end - $start),
            'line' => $line,
            'message' => sprintf('%s is declared after a method — declare all constants and properties at the top of the class, before any methods.', $label),
            'snippet' => trim(explode("\n", $content)[$line - 1] ?? ''),
            'symbol' => 'member-order:' . $kind . ':' . $name,
            'penance' => sprintf('Hoisted %s above the first method', strtolower($label)),
        ];
    }

    /**
     * The earliest file offset of a node, reaching back over its docblock and
     * attribute comments so a moved declaration carries them along.
     */
    private function nodeStart(Node $node): int
    {
        $start = $node->getStartFilePos();

        foreach ($node->getComments() as $comment) {
            $start = min($start, $comment->getStartFilePos());
        }

        return $start;
    }

    /**
     * Offset of the start of the line containing $pos.
     */
    private function lineStart(string $content, int $pos): int
    {
        $newline = strrpos(substr($content, 0, $pos), "\n");

        return $newline === false ? 0 : $newline + 1;
    }

    /**
     * Exclusive end of a declaration's move span: through the end of its line,
     * plus a single trailing blank line if present, so members keep their
     * separation when hoisted and leave no double blank behind.
     */
    private function spanEnd(string $content, int $endPos): int
    {
        $len = strlen($content);
        $i = $endPos + 1;

        while ($i < $len && $content[$i] !== "\n") {
            $i++;
        }

        if ($i < $len) {
            $i++;
        }

        $j = $i;

        while ($j < $len && ($content[$j] === ' ' || $content[$j] === "\t")) {
            $j++;
        }

        if ($j < $len && $content[$j] === "\n") {
            $i = $j + 1;
        }

        return $i;
    }
}
