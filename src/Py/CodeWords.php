<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\Assign;
use JesseGall\CodeCommandments\Py\Node\AugAssign;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Import;
use JesseGall\CodeCommandments\Py\Node\MatchStmt;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Raise;
use JesseGall\CodeCommandments\Py\Node\Return_;
use JesseGall\CodeCommandments\Py\Node\TryStmt;
use JesseGall\CodeCommandments\Py\Node\WhileLoop;
use JesseGall\CodeCommandments\Py\Node\With;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * The words a Python statement's own head spells — its names, attributes, keywords and string literals, and
 * the plain-English name of the construct — stemmed for comparison with prose. A nested body is not read.
 * The Python twin of {@see \JesseGall\CodeCommandments\Ast\Support\CodeWords}.
 */
final class CodeWords
{
    /**
     * What each construct says in English, so "loop over the orders" measures against a `for` and "fail when
     * empty" against a `raise`.
     *
     * @var array<class-string<Node>, list<string>>
     */
    private const array KEYWORDS = [
        ForLoop::class => ['for', 'loop', 'iterate', 'every', 'each'],
        WhileLoop::class => ['while', 'loop', 'until', 'repeat'],
        IfStmt::class => ['if', 'when', 'check', 'whether', 'otherwise'],
        Return_::class => ['return', 'give', 'result'],
        Raise::class => ['raise', 'throw', 'fail', 'error'],
        TryStmt::class => ['try', 'catch', 'handle'],
        MatchStmt::class => ['match', 'case', 'branch'],
        With::class => ['with', 'open'],
        Import::class => ['import'],
        ClassDef::class => ['class'],
        FunctionDef::class => ['function', 'method'],
        Assign::class => ['set', 'assign', 'store'],
        AnnAssign::class => ['set', 'assign', 'store'],
        AugAssign::class => ['add', 'increase', 'update'],
    ];

    /**
     * @return list<string>
     */
    public static function of(Node $node): array
    {
        $spelled = self::KEYWORDS[$node::class] ?? [];

        foreach ($node->expressions() as $expression) {
            foreach ($expression->flatten() as $part) {
                $spelled = [...$spelled, ...self::spelled($part)];
            }
        }

        return array_values(array_unique(Prose::words(implode(' ', $spelled))));
    }

    /**
     * The text $part itself spells — a name, an attribute, a keyword, a string.
     *
     * @return list<string>
     */
    private static function spelled(Expr $part): array
    {
        if ($part->is(ExprKind::Name) || $part->is(ExprKind::Attribute) || $part->is(ExprKind::Keyword)) {
            return [(string) $part->get('name')];
        }

        return $part->literalType() === LiteralType::String ? [(string) $part->get('value')] : [];
    }
}
