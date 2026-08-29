<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\PhpTypes\Option;

/**
 * One decoded line of a transcript, read semantically — the sibling of {@see
 * \JesseGall\CodeCommandments\Hooks\HookEvent} for the file the harness writes rather than the payload it
 * sends. It is the ONE place the transcript's own field names are known, so nothing above it handles a
 * raw array or learns that a tool result hides under `type: "user"`.
 */
final readonly class Record
{
    /**
     * @param  array<string, mixed>  $fields
     */
    private function __construct(private array $fields) {}

    /**
     * The record $json holds, absent when the line is not one — a blank line, or something this format
     * did not write.
     *
     * @return Option<self>
     */
    public static function decode(string $json): Option
    {
        $fields = json_decode(trim($json), true);

        return is_array($fields) ? Option::some(new self($fields)) : Option::none();
    }

    public function type(): string
    {
        return $this->text('type');
    }

    public function subtype(): string
    {
        return $this->text('subtype');
    }

    /**
     * Where the words came from — `typed`/`queued` for a human at the prompt, `system`/`sdk` for the loop
     * speaking in the user's turn.
     */
    public function promptSource(): string
    {
        return $this->text('promptSource');
    }

    /**
     * When the line was written, absent on the bookkeeping rows that carry no message.
     *
     * @return Option<string>
     */
    public function at(): Option
    {
        return Option::fromTruthy($this->text('timestamp'));
    }

    /**
     * Was this synthesized by the loop rather than typed by anybody?
     */
    public function isSynthesized(): bool
    {
        return $this->flag('isMeta');
    }

    /**
     * Is this a tool's output, echoed back in the user's turn? It is the commonest line in a transcript
     * by far, and the least worth reading.
     */
    public function isToolResult(): bool
    {
        return isset($this->fields['toolUseResult']);
    }

    /**
     * Is this what a compaction rewrote the conversation into?
     */
    public function isCompactSummary(): bool
    {
        return $this->flag('isCompactSummary');
    }

    /**
     * What was said. A message's content is either a plain string or a list of parts, of which only the
     * text ones were ever words; a line with neither said nothing.
     */
    public function said(): string
    {
        if (! $this->hasContent()) {
            return '';
        }

        $content = $this->fields['message']['content'] ?? $this->fields['content'];

        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text') {
                $parts[] = (string) ($part['text'] ?? '');
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * Does this line carry anything that was said at all? The bookkeeping rows do not.
     */
    public function hasContent(): bool
    {
        return isset($this->fields['message']['content']) || isset($this->fields['content']);
    }

    private function text(string $field): string
    {
        return isset($this->fields[$field]) && is_scalar($this->fields[$field]) ? (string) $this->fields[$field] : '';
    }

    private function flag(string $field): bool
    {
        return ($this->fields[$field] ?? null) === true;
    }
}
