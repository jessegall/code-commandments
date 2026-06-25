<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * Unit enum used by the bidirectional suffix-match scenario. Its short
 * name "Verb" is a SUFFIX of identifiers like `$broadcastVerb`.
 */
enum Verb
{
    case Run;
    case Walk;
}
