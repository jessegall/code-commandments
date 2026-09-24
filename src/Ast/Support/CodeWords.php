<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Support\Construct;
use JesseGall\CodeCommandments\Support\Prose;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;

/**
 * The words a piece of code SPELLS — every identifier, class name and string literal in a statement's
 * own head, plus the plain-English name of the construct itself ({@see CONSTRUCTS}: a `foreach` spells
 * "loop", a `Throw_` spells "fail"), reduced to comparable stems by {@see Prose}. The single home of
 * "what does this code already say in words". Reading stops at the head — a nested {@see Stmt} says its
 * own words — so a `foreach` spells its subject, and a long body stays out of the comparison.
 */
final class CodeWords
{
    /**
     * The construct each node is, and the keywords PHP spells it with — so "loop over the orders" measures
     * against a `foreach` and "fail when empty" against a `throw`. A node outside this map speaks through the
     * identifiers it contains.
     *
     * @var array<class-string<Node>, array{Construct, list<string>}>
     */
    private const array CONSTRUCTS = [
        Stmt\Foreach_::class => [Construct::Loop, ['foreach']],
        Stmt\For_::class => [Construct::Loop, ['for']],
        Stmt\While_::class => [Construct::ConditionalLoop, ['while']],
        Stmt\Do_::class => [Construct::ConditionalLoop, ['do']],
        Stmt\If_::class => [Construct::Condition, ['if']],
        Stmt\ElseIf_::class => [Construct::Condition, ['elseif']],
        Stmt\Else_::class => [Construct::Otherwise, ['else']],
        Stmt\Return_::class => [Construct::Return, ['return']],
        Stmt\Break_::class => [Construct::Break, ['break']],
        Stmt\Continue_::class => [Construct::Continue, ['continue']],
        Stmt\Switch_::class => [Construct::Branch, ['switch']],
        Stmt\TryCatch::class => [Construct::Attempt, ['try']],
        Stmt\Catch_::class => [Construct::Recovery, ['catch']],
        Stmt\Throw_::class => [Construct::Failure, ['throw']],
        Stmt\Unset_::class => [Construct::Removal, ['unset']],
        Stmt\Echo_::class => [Construct::Output, ['echo']],
        Stmt\Class_::class => [Construct::Type, ['class']],
        Stmt\Interface_::class => [Construct::Contract, ['interface']],
        Stmt\Trait_::class => [Construct::Type, ['trait']],
        Stmt\Enum_::class => [Construct::Type, ['enum']],
        Stmt\ClassMethod::class => [Construct::Method, ['function']],
        Stmt\Function_::class => [Construct::Method, ['function']],
        Stmt\Property::class => [Construct::Field, []],
        Stmt\ClassConst::class => [Construct::Constant, ['const']],
        Node\Expr\Throw_::class => [Construct::Failure, ['throw']],
        Node\Expr\Match_::class => [Construct::Branch, ['match']],
        Node\Expr\New_::class => [Construct::Creation, ['new']],
        Node\Expr\Assign::class => [Construct::Assignment, []],
        Node\Expr\Ternary::class => [Construct::Condition, []],
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

        foreach (Construct::wordsOf(self::CONSTRUCTS, $node::class) as $keyword) {
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
