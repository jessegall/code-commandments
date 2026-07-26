<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The MOOD of a method name — is its first word an imperative command (`hide`, `open`, `write`) or a
 * third-person narration of one (`hides`, `opens`, `writes`)? English cannot answer that from shape
 * alone: `names` and `hides` end alike, and `process`, `pass`, `focus` end in the same letter as a
 * narration. So the question is asked of a LEXICON of verb stems — the closed, curated set below —
 * and a token whose stem is not in it is left alone. The single home of that judgment, shared by the
 * rule and by the suggestion it prints.
 */
final class VerbMood
{
    /**
     * Prefixes that make a name read as a QUESTION — the only non-imperative mood a method may wear,
     * and then only when it answers `bool`.
     *
     * @var list<string>
     */
    public const array QUESTION_PREFIXES = [
        // Modals and auxiliaries — the words English opens a yes/no question with. The list stops
        // there: `needs`/`wants`/`requires`/`allows` are third-person verbs, so they belong on the
        // other side of this rule.
        'is', 'are', 'was', 'were', 'has', 'have', 'had', 'can', 'could', 'should', 'must', 'may',
        'might', 'will', 'would', 'does', 'do', 'did', 'awaits',
    ];

    /**
     * Verb STEMS whose third-person form is a narration where a command belongs. Curated, and read as
     * an allow-list of what the rule may speak about: a stem outside it stays silent, so the list adds
     * precision as it grows. That is what keeps plural nouns (`names`, `bindings`, `fields`) and
     * imperatives ending in `s` (`process`, `pass`, `dismiss`, `focus`) outside its reach.
     *
     * @var list<string>
     */
    private const array VERBS = [
        // appearance / lifecycle
        'hide', 'reveal', 'show', 'display', 'render', 'draw', 'paint', 'open', 'close', 'expand',
        'collapse', 'enter', 'exit', 'leave', 'start', 'stop', 'begin', 'end', 'pause', 'resume',
        'mount', 'unmount', 'boot', 'shutdown', 'spin', 'animate', 'fade', 'flash', 'blink',
        // data / persistence
        'write', 'read', 'save', 'store', 'persist', 'load', 'fetch', 'pull', 'push', 'send', 'receive',
        'publish', 'emit', 'announce', 'broadcast', 'dispatch', 'queue', 'flush', 'sync', 'import',
        'export', 'upload', 'download', 'cache', 'log', 'record', 'report', 'track', 'count',
        // mutation
        'add', 'remove', 'delete', 'drop', 'clear', 'reset', 'update', 'create', 'make', 'build',
        'register', 'unregister', 'bind', 'unbind', 'attach', 'detach', 'link', 'unlink', 'connect',
        'disconnect', 'assign', 'apply', 'set', 'put', 'insert', 'append', 'prepend', 'replace',
        'rename', 'move', 'copy', 'merge', 'split', 'sort', 'filter', 'reorder', 'toggle', 'swap',
        // behaviour / control
        'run', 'execute', 'perform', 'handle', 'process', 'resolve', 'reject', 'accept', 'approve',
        'cancel', 'abort', 'retry', 'refresh', 'reload', 'redirect', 'forward', 'route', 'call',
        'invoke', 'trigger', 'fire', 'notify', 'warn', 'fail', 'throw', 'catch', 'guard', 'protect',
        'validate', 'verify', 'check', 'test', 'assert', 'ensure', 'require', 'expect', 'wait',
        // domain-ish, still verbs
        'pay', 'charge', 'refund', 'ship', 'deliver', 'pick', 'pack', 'print', 'scan', 'quote',
        'book', 'reserve', 'release', 'lock', 'unlock', 'grant', 'revoke', 'invite', 'join', 'follow',
        'own', 'hold', 'carry', 'cover', 'wrap', 'unwrap', 'mark', 'tag', 'label', 'name', 'title',
        'describe', 'explain', 'answer', 'ask', 'reply', 'respond', 'echo', 'print', 'dump',
    ];

    /**
     * Prepositions that turn a name into a RELATION — `startsWith`, `endsWith`, `compliesWith`. A
     * fluent method whose name relates the receiver to what it is handed is a specification, not an
     * order: `->startsWith('a')` states a constraint, and the third person is the correct English for
     * it. The same words after a VOID verb are still an order (`runOn($secret)`), so this list only
     * ever excuses a fluent declaration.
     *
     * @var list<string>
     */
    private const array PREPOSITIONS = [
        'with', 'without', 'for', 'on', 'at', 'in', 'into', 'to', 'from', 'by', 'of', 'off', 'over',
        'under', 'above', 'below', 'between', 'against', 'through', 'across', 'after', 'before',
        'during', 'until', 'toward', 'towards', 'upon', 'via', 'per', 'like', 'as',
    ];

    /**
     * Does $name relate the receiver to something — a verb followed by a preposition, as in
     * `startsWith`, `endsWith`, `compliesWith`?
     */
    public static function isRelationalCompound(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        $rest = CamelCase::afterLeadingToken($name);

        return $rest !== '' && in_array(strtolower(CamelCase::leadingToken(lcfirst($rest))), self::PREPOSITIONS, true);
    }

    /**
     * Is $name's first word a third-person narration of a verb this lexicon knows — `hides`,
     * `entersTestMode`, `reports`? False for a plural noun, for an unknown stem, and for a name that
     * already reads as an imperative.
     */
    public static function isThirdPerson(?string $name): bool
    {
        return self::stemOf($name) !== null;
    }

    /**
     * The imperative $name should have worn — `hides` → `hide`, `entersTestMode` → `enterTestMode`.
     * Returns $name unchanged when its first word is not a narration this lexicon knows.
     */
    public static function imperative(string $name): string
    {
        $stem = self::stemOf($name);

        return $stem === null ? $name : $stem . substr($name, strlen(CamelCase::leadingToken($name)));
    }

    /**
     * Does $name open with a question word — the mood a `bool` answer is allowed to wear?
     */
    public static function readsAsQuestion(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        $token = CamelCase::leadingToken($name);

        return $token !== '' && in_array(strtolower($token), self::QUESTION_PREFIXES, true);
    }

    /**
     * The verb stem behind a third-person leading token, or null when there isn't one: the token must
     * end in `s`, and what remains once that ending comes off must be a verb the lexicon knows.
     */
    private static function stemOf(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $token = strtolower(CamelCase::leadingToken($name));

        if (! str_ends_with($token, 's')) {
            return null;
        }

        foreach (self::candidates($token) as $candidate) {
            if (in_array($candidate, self::VERBS, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The stems a third-person form could have come from — `hides` → `hide`, `pushes` → `push`,
     * `carries` → `carry`.
     *
     * @return list<string>
     */
    private static function candidates(string $token): array
    {
        $candidates = [substr($token, 0, -1)];

        if (str_ends_with($token, 'es')) {
            $candidates[] = substr($token, 0, -2);
        }

        if (str_ends_with($token, 'ies')) {
            $candidates[] = substr($token, 0, -3) . 'y';
        }

        return $candidates;
    }
}
