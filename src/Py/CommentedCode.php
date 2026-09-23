<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ExprStmt;

/**
 * Whether a comment holds Python rather than prose.
 */
final class CommentedCode
{
    /**
     * Does $comment read as one Python statement from end to end — `total += rate`, `return order.total` —
     * rather than prose? Words strung together parse as several statements on one line, and a lone name is a
     * label, not code.
     */
    public static function isCode(string $comment): bool
    {
        $text = trim($comment);

        if ($text === '') {
            return false;
        }

        $body = Parser::module($text)->body;

        if (count($body) !== 1 || $body[0]->end < strlen($text)) {
            return false;
        }

        return ! ($body[0] instanceof ExprStmt && $body[0]->value->is(ExprKind::Name));
    }
}
