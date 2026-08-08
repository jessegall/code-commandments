<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

/**
 * The reserved words the JS/TS grammars read, named once — the keyword twin of {@see Token}, and
 * shared by both readers for the same reason the punctuation is: a parser that writes
 * `atId('interface')` at each of fifty call sites has the language's spelling scattered through it,
 * where a typo is a rule that quietly never fires rather than an error. Naming them makes the
 * grammar's vocabulary the one thing you can read off this file.
 */
final class Keyword
{
    // ---- declarations ---------------------------------------------------------
    public const string IMPORT = 'import';
    public const string EXPORT = 'export';
    public const string DEFAULT = 'default';
    public const string FROM = 'from';
    public const string AS = 'as';
    public const string INTERFACE = 'interface';
    public const string TYPE = 'type';
    public const string CLASS_ = 'class';
    public const string EXTENDS = 'extends';
    public const string IMPLEMENTS = 'implements';
    public const string FUNCTION = 'function';
    public const string CONST = 'const';
    public const string LET = 'let';
    public const string VAR = 'var';
    public const string ENUM = 'enum';
    public const string NAMESPACE = 'namespace';
    public const string DECLARE = 'declare';

    // ---- statements -----------------------------------------------------------
    public const string IF = 'if';
    public const string ELSE = 'else';
    public const string SWITCH = 'switch';
    public const string CASE = 'case';
    public const string FOR = 'for';
    public const string WHILE = 'while';
    public const string DO = 'do';
    public const string OF = 'of';
    public const string IN = 'in';
    public const string RETURN = 'return';
    public const string THROW = 'throw';
    public const string TRY = 'try';
    public const string CATCH = 'catch';
    public const string FINALLY = 'finally';
    public const string BREAK = 'break';
    public const string CONTINUE = 'continue';

    // ---- expressions ----------------------------------------------------------
    public const string NEW = 'new';
    public const string AWAIT = 'await';
    public const string ASYNC = 'async';
    public const string YIELD = 'yield';
    public const string TYPEOF = 'typeof';
    public const string INSTANCEOF = 'instanceof';
    public const string GET = 'get';
    public const string SET = 'set';
    public const string STATIC_ = 'static';

    // ---- absence --------------------------------------------------------------
    public const string NULL = 'null';
    public const string UNDEFINED = 'undefined';
    public const string TRUE = 'true';
    public const string FALSE = 'false';

    /**
     * The two ways TypeScript spells "no value" — what an absence rule tests against, so neither
     * spelling can be checked without the other.
     */
    public const array ABSENCE = [self::NULL, self::UNDEFINED];

    /**
     * The literals whose value is the word itself, so the lexer's identifier IS the literal.
     */
    public const array VALUE_LITERALS = [self::TRUE, self::FALSE, self::NULL, self::UNDEFINED];

    /**
     * The words that begin a STATEMENT rather than an expression — what tells the parser it is
     * looking at control flow before it tries to read a value.
     */
    public const array STATEMENT_LEADS = [
        self::IF, self::SWITCH, self::FOR, self::WHILE, self::DO, self::RETURN,
        self::THROW, self::TRY, self::BREAK, self::CONTINUE,
    ];

    /**
     * Does $word spell "no value"? Both spellings are one question, so neither can be tested
     * without the other — which is the whole reason an absence rule asks this rather than
     * comparing against `'null'` and hoping someone remembered `undefined`.
     */
    public static function isAbsence(string $word): bool
    {
        return in_array($word, self::ABSENCE, true);
    }

    /**
     * Is $word a literal whose VALUE is the word itself — `true`, `false`, `null`, `undefined`? What
     * tells the lexer's identifier apart from a name that merely looks like one.
     */
    public static function isValueLiteral(string $word): bool
    {
        return in_array($word, self::VALUE_LITERALS, true);
    }

    /**
     * Does $word begin a STATEMENT rather than an expression? What tells the parser it is looking at
     * control flow before it tries to read a value.
     */
    public static function startsStatement(string $word): bool
    {
        return in_array($word, self::STATEMENT_LEADS, true);
    }
}
