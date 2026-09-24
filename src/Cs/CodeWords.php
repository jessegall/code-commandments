<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\Construct;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * The words a C# statement's own head spells — its names and literals, and the plain-English name of each
 * construct in it — stemmed for comparison with prose. A nested statement says its own words. The C# twin of
 * {@see \JesseGall\CodeCommandments\Ast\Support\CodeWords}.
 */
final class CodeWords
{
    /**
     * The construct each node kind is, and the keywords C# spells it with — so "loop over the orders" measures
     * against a `foreach` and "fail when empty" against a `throw`.
     *
     * @var array<string, array{Construct, list<string>}>
     */
    private const array CONSTRUCTS = [
        'ForEachStatement' => [Construct::Loop, ['foreach']],
        'ForStatement' => [Construct::Loop, ['for']],
        'WhileStatement' => [Construct::ConditionalLoop, ['while']],
        'DoStatement' => [Construct::ConditionalLoop, ['do']],
        'IfStatement' => [Construct::Condition, ['if']],
        'ElseClause' => [Construct::Otherwise, ['else']],
        'ConditionalExpression' => [Construct::Condition, []],
        'ReturnStatement' => [Construct::Return, ['return']],
        'YieldReturnStatement' => [Construct::Return, ['yield', 'return']],
        'BreakStatement' => [Construct::Break, ['break']],
        'ContinueStatement' => [Construct::Continue, ['continue']],
        'SwitchStatement' => [Construct::Branch, ['switch']],
        'SwitchExpression' => [Construct::Branch, ['switch']],
        'TryStatement' => [Construct::Attempt, ['try']],
        'CatchClause' => [Construct::Recovery, ['catch']],
        'ThrowStatement' => [Construct::Failure, ['throw']],
        'ThrowExpression' => [Construct::Failure, ['throw']],
        'ObjectCreationExpression' => [Construct::Creation, ['new']],
        'ImplicitObjectCreationExpression' => [Construct::Creation, ['new']],
        'SimpleAssignmentExpression' => [Construct::Assignment, []],
        'LocalDeclarationStatement' => [Construct::Assignment, []],
        'AddAssignmentExpression' => [Construct::Accumulation, []],
        'PostIncrementExpression' => [Construct::Accumulation, ['increment']],
        'PreIncrementExpression' => [Construct::Accumulation, ['increment']],
        'UsingStatement' => [Construct::Scope, ['using']],
    ];

    /**
     * Every word $node's own head spells, stemmed and de-duplicated.
     *
     * @return list<string>
     */
    public static function of(Node $node): array
    {
        return array_values(array_unique(Prose::words(implode(' ', self::spelled($node)))));
    }

    /**
     * The text $node and the parts of its head spell — its construct's words, its name, its literal.
     *
     * @return list<string>
     */
    private static function spelled(Node $node): array
    {
        $head = array_filter($node->children, static fn (Node $child): bool => ! in_array($child->role, ['statement', 'member'], true));

        return [
            ...Construct::wordsOf(self::CONSTRUCTS, $node->kind),
            ...array_filter([$node->name, $node->text], static fn (?string $text): bool => $text !== null),
            ...array_merge([], ...array_map(self::spelled(...), array_values($head))),
        ];
    }
}
