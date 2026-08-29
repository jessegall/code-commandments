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

            TAG WHAT YOU SAY. Put the tag at the front of the message, as its first characters:

            {$tags}

            The user reads these, so they are written as words and only these five are ever typed. Anything
            else you want the journal to hold is RECORDED rather than said:

              {$binary} journal remember "<a fact you must not lose>"

            A remembered fact outlives every compaction and is written into the summariser's own
            instructions, so it reaches the far side whatever else is dropped. Pin the user's standing
            rulings, the constraint you keep nearly breaking, and the decision behind the work in hand.

            READ IT BACK — after a compaction, before you touch anything:

              {$binary} journal              the conversation since the last compaction
              {$binary} journal --back=1     the stretch the last summary replaced
              {$binary} journal user         only the user's own words, in full
              {$binary} journal search "<term>"
              {$binary} journal open         work you started and never closed
              {$binary} journal verify       whether the record agrees with what you SAID

            That last one answers the question you cannot answer from the inside. "I stopped tagging" and
            "I tagged and the tool did not hear me" are the same silence from where you sit, and the second
            leaves you believing you closed work that is still open. Run it when something feels missing.
            TEXT;
    }
}
