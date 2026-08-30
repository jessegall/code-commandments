<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * One dispatch a moment asked for — WHICH moment, WHAT it carried, WHO is to act and the PROCEDURE they
 * carry out. It is written when the moment happens and read when the orchestrator comes to a stop, so it
 * is the whole answer to "did it fire?": every layer above the old transport reported success all evening
 * while nothing ran, because nothing was ever written down.
 */
final readonly class Dispatched
{
    /**
     * What separates the fields on a line. A middle dot, because no field can contain one and a person
     * can still read the row without a tool.
     */
    private const string SEPARATOR = ' · ';

    /**
     * @param  string  $source  what is known about the subject beyond its NAME — where the agent goes to
     *                          see the thing itself. A subject a reader cannot reach is a name, not a
     *                          subject, and an agent handed one goes looking for work nobody did.
     */
    public function __construct(
        public string $at,
        public string $moment,
        public string $subject,
        public string $agent,
        public string $procedure,
        public string $source,
    ) {}

    public function toLine(): string
    {
        return implode(self::SEPARATOR, [
            $this->at,
            $this->moment,
            $this->subject,
            $this->agent,
            $this->procedure,
            self::onOneLine($this->source),
        ]);
    }

    /**
     * A row is ONE line with a fixed number of fields, so a pointer that carries a newline or the
     * separator would silently become a different row. It is flattened rather than escaped: this is a
     * place to look, and the shape of the whitespace in it carries nothing.
     */
    private static function onOneLine(string $source): string
    {
        return trim(str_replace(["\r", "\n", self::SEPARATOR], [' ', ' ', ' - '], $source));
    }

    /**
     * @return Option<self>
     */
    public static function fromLine(string $line): Option
    {
        $fields = explode(self::SEPARATOR, trim($line));

        if (count($fields) !== 6) {
            return Option::none();
        }

        return Option::some(new self($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[5]));
    }

    /**
     * Is this the same piece of work as $other? Two moments naming one agent, one procedure and one
     * subject are one dispatch however often they fire — a commit hook that fires twice on one sha must
     * not leave the orchestrator two identical agents to start.
     */
    public function isSameAs(self $other): bool
    {
        return $this->moment === $other->moment
            && $this->subject === $other->subject
            && $this->agent === $other->agent
            && $this->procedure === $other->procedure;
    }

    public function render(): string
    {
        return sprintf('%s  %-16s %-28s %s → %s', $this->at, $this->moment, $this->subject, $this->agent, $this->procedure);
    }
}
