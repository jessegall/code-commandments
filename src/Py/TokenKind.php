<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * What a Python {@see Token} is. Newlines and indentation are tokens of their own, because in Python
 * they are the grammar: a logical line ends in NEWLINE, and a block is what sits between INDENT and
 * DEDENT.
 */
enum TokenKind: string
{
    case Name = 'name';
    case Number = 'number';
    case String = 'string';
    case Op = 'op';
    case Newline = 'newline';
    case Indent = 'indent';
    case Dedent = 'dedent';
    case Comment = 'comment';
    case EndMarker = 'endmarker';
}
