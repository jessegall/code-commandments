<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * The kinds of comment C# has, as the bridge names them.
 */
enum CommentKind: string
{
    case Line = 'line';
    case Block = 'block';
    case Documentation = 'doc';
}
