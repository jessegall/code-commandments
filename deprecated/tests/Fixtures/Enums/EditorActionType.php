<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Enums;

/**
 * Fixture for #191: a reusable enum that lives in its OWN namespace, so a bare
 * `--input enum-class=EditorActionType` short-name reuse must resolve to this
 * FQCN and import it (the file being repented is in a different namespace).
 */
enum EditorActionType: string
{
    case Insert = 'insert';

    case Delete = 'delete';
}
