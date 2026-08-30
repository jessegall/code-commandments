<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Support\Binary;

/**
 * How the journal is used, in one place. Every refusal points HERE rather than restating it: a hook that
 * has to teach the whole discipline in its own reason grows into an essay nobody reads, and the several
 * copies drift. So a hook says what is wrong in a line and names the command, and this answers it.
 */
final class Brief
{
    public function __construct(private readonly string $root) {}

    public function render(): string
    {
        $binary = Binary::in($this->root);
        $tags = Tag::vocabulary();

        return <<<TEXT
            THE JOURNAL — how a session survives its own compaction.

            A compaction rewrites the conversation into a summary. The summary keeps what was DONE and
            loses what was DECIDED: the ruling the user gave once, the approach you changed your mind
            about, the work you were half-way through. The journal is how those survive.

            DECLARE YOUR WORK. Before you change anything, say what you are starting; when it is done, say
            so. The pair is what makes unfinished work visible on the far side of a compaction — a start
            with no end is the first thing the next reader needs to know.

              [!start] making Drilldown a composition
              [!end] making Drilldown a composition

            TAG WHAT YOU SAY. Put the tag at the START OF A LINE — anywhere in the message, so long as
            it opens its own line:

            {$tags}

            The user reads these, so they are written as words and only these are ever typed. Anything
            else you want the journal to hold is RECORDED rather than said:

              {$binary} journal remember "<a fact you must not lose>"

            WHAT EARNS A PIN. A tag is free — it rides on a message you were sending anyway, so tag
            generously. A pin is not free. Only the last TWELVE live pins are written into the
            summariser's own instructions, so the thirteenth does not merely add noise: it pushes the
            first one out, silently. A pin that did not need to be there DELETES one that did.

            So ask three questions, and pin only on three yeses:

              1. Did somebody DECIDE it? A ruling, a constraint, a choice and the reason for it — not
                 something the code, the diff or the board already states.
              2. Would the next reader get it WRONG without it? Not slower — WRONG. If reading one file
                 answers it in a minute, that file is the record and no pin is needed.
              3. Will it still be true tomorrow? A pin is carried forward wearing full confidence, and
                 nothing corrects it but you.

              PIN  "the user ruled motion.ts FORBIDDEN — the CSS transition replaces it"
              PIN  "chose the tokenizer over a regex: a regex cannot see nesting"
              PIN  "abandoned the AST approach — php-parser drops the attribute"
              NOT  "fixed the import in motion.ts"      done work — the diff says it; tag the message
              NOT  "judge: 412 findings"                a measurement, stale within the hour
              NOT  "two workers in flight at v4.294.0"  a status — the board holds it
              NOT  "16 primitives, not 14"              a count; it drifts with nothing touching it
              NOT  "now reading src/Cli/Journal"        narration — say it, do not pin it

            Three homes, and only the first is a pin. A fact or a ruling must SURVIVE — pin it. A
            finding, a defect, work still owed must be WORKED — it belongs to the plan. A status — who
            holds what, what a tool measured — must be CURRENT, and belongs to the board. The plan and
            the board are updated as the build moves; a pin is only corrected if you remember to.

            WRITE IT SO IT CAN BE FOUND. One line: the fact, then its reason, in the same breath. Short
            — but carrying the word a later reader would search for, because the pin is the handle and
            the transcript is the body, and `{$binary} journal search "<that word>"` fetches the rest.
            "do not use that approach" names nothing, so it can be neither found nor checked.

            CORRECT A PIN, NEVER LEAVE IT STANDING. Question 3 is why this command exists. Even a pin you
            were right to make can stop being true — a ruling is overturned, a constraint is lifted — and
            it then sits beside the facts that still hold, wearing the same confidence, so the next
            reader inherits a settled question as an open one. The moment a pin stops being true, say
            what is:

              {$binary} journal pins                              the numbered list — live and struck
              {$binary} journal remember "<the fact now>" --supersedes=<n>

            Nothing is deleted. Pin <n> stays in the record marked as superseded, the new pin names it —
            so the correction is readable, which is the half worth keeping — and only the new one is
            carried across a compaction.

            READ IT BACK — after a compaction, before you touch anything:

              {$binary} journal              the conversation since the last compaction
              {$binary} journal --back=1     the stretch the last summary replaced
              {$binary} journal user         only the user's own words, in full
              {$binary} journal search "<term>"
              {$binary} journal pins         every pinned fact, numbered, the struck ones marked
              {$binary} journal open         work you started and never closed
              {$binary} journal verify       whether the record agrees with what you SAID

            That last one answers the question you cannot answer from the inside. "I stopped tagging" and
            "I tagged and the tool did not hear me" are the same silence from where you sit, and the second
            leaves you believing you closed work that is still open. Run it when something feels missing.
            TEXT;
    }
}
