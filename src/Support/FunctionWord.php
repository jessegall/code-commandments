<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Is a token a FUNCTION word — a modal/auxiliary verb (`is`/`has`/`should`), a quantifier (`total`/`min`),
 * an action verb (`add`/`close`), or a state adjective (`running`/`auto`) — rather than a content-word NOUN
 * that could name a sub-entity? A flat-cluster analysis nests `wire{…}` but never `total{…}` or `is{…}`,
 * because the prefix there is grammar. Detecting nouns needs a dictionary; the non-noun set is small and
 * closed, so we test for THAT — the list is the calibration surface, extended as false positives surface.
 */
final class FunctionWord
{
    /**
     * Tokens that never name a sub-entity — matched lower-cased against a name's leading token.
     *
     * @var list<string>
     */
    private const array NON_ENTITY = [
        // modal + auxiliary verbs (and their boolean-flag idioms)
        'is', 'are', 'was', 'be', 'been', 'has', 'have', 'had', 'can', 'could', 'should', 'would',
        'will', 'shall', 'may', 'might', 'must', 'do', 'does', 'did',
        // quantifiers / determiners
        'no', 'all', 'any', 'some', 'each', 'every', 'none', 'total', 'sum', 'count', 'num', 'min',
        'max', 'first', 'last', 'next', 'prev', 'only',
        // common action verbs (UI / CRUD)
        'add', 'remove', 'delete', 'close', 'open', 'move', 'copy', 'import', 'export', 'discard',
        'confirm', 'cancel', 'save', 'load', 'run', 'sort', 'filter', 'toggle', 'show', 'hide', 'get',
        'set', 'fetch', 'send', 'submit', 'reset', 'clear', 'apply', 'select', 'edit', 'update', 'create',
        'connect', 'disconnect', 'replay', 'zoom', 'scroll', 'refresh', 'pick',
        // state adjectives
        'running', 'booting', 'loading', 'pending', 'unsaved', 'empty', 'used', 'auto', 'required',
        'active', 'enabled', 'disabled', 'dynamic', 'advisory', 'quick', 'current',
        // prepositions / misc grammar
        'to', 'of', 'in', 'on', 'at', 'by', 'for', 'with', 'from',
    ];

    /**
     * Is $token a non-noun function word — grammar, not a sub-entity name?
     */
    public static function isNonEntity(string $token): bool
    {
        return in_array(strtolower($token), self::NON_ENTITY, true);
    }
}
