<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * The version a project could move to, and whether that was MEASURED at all — a type rather than a string
 * because the answer has three states and not two: a version, nothing published, or nobody found out.
 * A version this could not read is never guessed; {@see COULD_NOT_MEASURE} is a real answer, and it
 * carries the reason it is the answer.
 */
final readonly class Release
{
    /**
     * What a version reads as when nothing measured it — said in full, because a dash or a `?` in a
     * column invites the reader to supply their own number.
     */
    public const string COULD_NOT_MEASURE = 'COULD NOT MEASURE';

    /**
     * @param  ?string  $unmeasurable  why nothing was read — composer missing, the network down, a
     *                                 repository unreachable. Absent when $version was genuinely read.
     */
    private function __construct(
        public string $version,
        private ?string $unmeasurable = null,
    ) {}

    public static function measured(string $version): self
    {
        return new self($version);
    }

    public static function unmeasurable(string $why): self
    {
        return new self('', $why);
    }

    public function isMeasurement(): bool
    {
        return $this->unmeasurable === null;
    }

    /**
     * Why nothing was read — absent when something was. The caller reads it the way it reads any absence,
     * so an unread reason can never reach a screen as an empty line pretending to be an explanation.
     *
     * @return Option<string>
     */
    public function reason(): Option
    {
        return Option::fromNullable($this->unmeasurable);
    }

    /**
     * Is $installed behind this release? Only ever asked of a measurement — an unread version is behind
     * nothing and ahead of nothing, and answering `false` there would read as "you are up to date".
     */
    public function isAheadOf(string $installed): bool
    {
        return $this->isMeasurement() && version_compare(ltrim($installed, 'v'), ltrim($this->version, 'v'), '<');
    }

    /**
     * The version, or the words that say there isn't one.
     */
    public function render(): string
    {
        return $this->isMeasurement() ? $this->version : self::COULD_NOT_MEASURE;
    }
}
