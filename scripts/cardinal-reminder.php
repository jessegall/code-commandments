<?php

declare(strict_types=1);

/**
 * A dev-only PostToolUse hook for working ON code-commandments itself: every {@see INTERVAL} tool uses it
 * re-surfaces the cardinal rule — never hand-roll parsing OR rewriting; compose the engine. It exists
 * because that rule is the easiest to cheat under momentum (a quick `preg_`/`strpos`/`ctype` "just this
 * once"), and the guard test only catches it after the fact. Wire it in `.claude/settings.local.json`:
 *
 *   "hooks": { "PostToolUse": [ { "hooks": [ { "type": "command",
 *       "command": "php \"$CLAUDE_PROJECT_DIR/scripts/cardinal-reminder.php\"" } ] } ] }
 *
 * It reads the hook payload on STDIN (ignored), counts in a temp file keyed to this checkout, and prints
 * a `PostToolUse` additionalContext block on the interval — silent otherwise, so it costs nothing between.
 */

const INTERVAL = 10;

// STDIN is the hook payload; we don't need it, just don't block on a TTY.
if (! stream_isatty(STDIN)) {
    stream_get_contents(STDIN);
}

$counter = sys_get_temp_dir() . '/cc-cardinal-' . md5(__DIR__) . '.count';
$count = 1 + (is_file($counter) ? (int) file_get_contents($counter) : 0);

if ($count < INTERVAL) {
    @file_put_contents($counter, (string) $count);

    exit(0);
}

@file_put_contents($counter, '0');

$reminder = <<<'TXT'
⚠️ CARDINAL RULE (CLAUDE.md) — never hand-roll parsing OR rewriting; compose the ENGINE, and if a tool is
missing, ADD it to the engine (reusably) rather than scrape/scribble the source in a detector or scribe.

DETECT through the engine:
  • backend — `Codebase` selectors → `Query` (where/reject) → `AstNode`/`NodeMatch` over php-parser; plus
    `TypeResolver` (real types through the receiver chain), `ReceiverResolver`, `Script`, the call graph.
  • frontend — the Vue tokenizer + `Vue\Expr\Parser` + `Vue\Query` → `ElementMatch`/`Expr`. Never regex a
    template or an expression.
  A package concept (Spatie Data, Laravel, TypeScript attrs) lives on its decorator node (`SpatieDataNode`…).

REWRITE through the engine: `Draft` for edits; `Span` owns ALL offset math (`indentAt`/`before`/`after`/
  `skipWhitespace`). No bespoke indentation/keyword-hunting/attribute-insertion scattered in a scribe.

BANNED in detectors/scribes (NoRegexInParsingLayerTest enforces it): `preg_*`, `strpos`/`strrpos`/`strstr`,
  and `ctype_*` char-lexing. A `preg`/`strpos`/`ctype` "just this once" IS the violation — reuse or extend
  the engine instead, and write the new tool with intent to reuse.
TXT;

echo json_encode([
    'suppressOutput' => true,
    'hookSpecificOutput' => [
        'hookEventName' => 'PostToolUse',
        'additionalContext' => $reminder,
    ],
]);

exit(0);
