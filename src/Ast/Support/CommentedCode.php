<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Is a comment PROSE, or CODE someone commented out? The parser decides: the line is offered to it as
 * an array element, so a disabled statement still reads as code with its trailing comma or semicolon
 * (`// new MandatedPaymentBuilder(0.83, 3.77),`), while an English sentence fails to parse. Dead code
 * kept in a comment is its own problem, and belongs to a different rule than the prose ones.
 */
final class CommentedCode
{
    private static ?Parser $parser = null;

    /**
     * Does $comment hold PHP rather than prose?
     */
    public static function isCode(string $comment): bool
    {
        $text = rtrim(ltrim($comment, "/# \t"), " \t,;");

        if ($text === '') {
            return false;
        }

        $errors = new Collecting;
        $statements = self::parser()->parse("<?php [{$text}];", $errors);

        return $errors->getErrors() === [] && $statements !== null;
    }

    private static function parser(): Parser
    {
        return self::$parser ??= (new ParserFactory)->createForNewestSupportedVersion();
    }
}
