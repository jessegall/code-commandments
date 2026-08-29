<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

/**
 * Who or what an {@see Entry} records. The three are read differently by the digest: a user's words are
 * never trimmed, an agent's are kept for their {@see Tag}, and a mark is a boundary the others are
 * gathered between.
 */
enum Kind: string
{
    /**
     * The user's own words — the thing a compaction summary loses first and the reason the journal exists.
     */
    case User = 'user';

    case Agent = 'agent';

    /**
     * A boundary: a compaction, a session start, or a fact pinned by hand.
     */
    case Mark = 'mark';
}
