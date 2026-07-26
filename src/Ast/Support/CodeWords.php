<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Support\Prose;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;

/**
 * The words a piece of code SPELLS — every identifier, class name and string literal in a statement's
 * own head, plus the plain-English name of the construct itself ({@see KEYWORDS}: a `foreach` spells
 * "loop", a `Throw_` spells "fail"), reduced to comparable stems by {@see Prose}. The single home of
 * "what does this code already say in words". Reading stops at the head — a nested {@see Stmt} says its
 * own words — so a `foreach` spells its subject, and a long body stays out of the comparison.
 */
final class CodeWords
{
    /**
     * What each construct says in English — the words a reader would use for it, so "loop over the
     * orders" measures against a `foreach` and "fail when empty" against a `throw`. Keyed by node
     * class; a construct outside this map speaks through the identifiers it contains.
     *
     * @var array<class-string<Node>, list<string>>
     */
    private const array KEYWORDS = [
        Stmt\Foreach_::class => ['foreach', 'loop', 'iterate', 'every'],
        Stmt\For_::class => ['for', 'loop', 'iterate', 'every'],
        Stmt\While_::class => ['while', 'loop', 'until', 'repeat'],
        Stmt\Do_::class => ['do', 'while', 'loop', 'repeat'],
        Stmt\If_::class => ['if', 'when', 'check', 'whether'],
        Stmt\ElseIf_::class => ['elseif', 'if', 'when', 'otherwise'],
        Stmt\Else_::class => ['else', 'otherwise'],
        Stmt\Return_::class => ['return', 'give', 'yield', 'result'],
        Stmt\Break_::class => ['break', 'stop', 'leave'],
        Stmt\Continue_::class => ['continue', 'skip', 'next'],
        Stmt\Switch_::class => ['switch', 'case', 'branch'],
        Stmt\TryCatch::class => ['try', 'catch', 'handle'],
        Stmt\Catch_::class => ['catch', 'handle', 'error'],
        Stmt\Throw_::class => ['throw', 'raise', 'fail', 'error'],
        Stmt\Unset_::class => ['unset', 'remove', 'drop', 'clear'],
        Stmt\Echo_::class => ['echo', 'print', 'output'],
        Stmt\Class_::class => ['class'],
        Stmt\Interface_::class => ['interface', 'contract'],
        Stmt\Trait_::class => ['trait'],
        Stmt\Enum_::class => ['enum'],
        Stmt\ClassMethod::class => ['method', 'function'],
        Stmt\Function_::class => ['function'],
        Stmt\Property::class => ['property', 'field'],
        Stmt\ClassConst::class => ['const', 'constant'],
        Node\Expr\Throw_::class => ['throw', 'raise', 'fail', 'error'],
        Node\Expr\Match_::class => ['match', 'case', 'branch'],
        Node\Expr\New_::class => ['new', 'create', 'make', 'build'],
        Node\Expr\Assign::class => ['set', 'assign', 'store'],
        Node\Expr\Ternary::class => ['if', 'when', 'otherwise'],
    ];

    /**
     * Every word $node's own head spells, stemmed and de-duplicated.
     *
     * @return list<string>
     */
    public static function of(Node $node): array
    {
        $words = [];
        self::harvest($node, $words, root: true);

        return array_values(array_unique($words));
    }

    /**
     * @param  list<string>  $words
     */
    private static function harvest(Node $node, array &$words, bool $root): void
    {
        if (! $root && $node instanceof Stmt) {
            return; // A nested body says its own words, not this statement's.
        }

        foreach (self::KEYWORDS[$node::class] ?? [] as $keyword) {
            $words[] = Prose::stem($keyword);
        }

        foreach (self::spelled($node) as $text) {
            foreach (Prose::words($text) as $word) {
                $words[] = $word;
            }
        }

        foreach (self::children($node) as $child) {
            self::harvest($child, $words, root: false);
        }
    }

    /**
     * The text this node itself spells — a name, an identifier, a variable, a literal string.
     *
     * @return list<string>
     */
    private static function spelled(Node $node): array
    {
        return match (true) {
            $node instanceof Identifier, $node instanceof Name => [$node->toString()],
            $node instanceof Variable => is_string($node->name) ? [$node->name] : [],
            $node instanceof String_ => [$node->value],
            default => [],
        };
    }

    /**
     * @return list<Node>
     */
    private static function children(Node $node): array
    {
        $children = [];

        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->$name;

            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }

        return $children;
    }
}
