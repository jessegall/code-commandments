<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use JsonSerializable;

/**
 * One comment of a file as the contract writes it: its text and span, where it belongs, and whether it is code.
 */
final readonly class Comment implements JsonSerializable
{
    /**
     * @param  array{int, int, int}  $span
     */
    public function __construct(
        public int $id,
        public string $kind,
        public string $text,
        public array $span,
        public Attachment $attachment,
        public bool $code,
    ) {}

    public function jsonSerialize(): array
    {
        $written = ['id' => $this->id, 'kind' => $this->kind, 'text' => $this->text, 'span' => $this->span];
        if ($this->attachment->owner !== null) {
            $written['attached'] = $this->attachment->owner;
        }
        if ($this->attachment->trailing) {
            $written['trailing'] = true;
        }
        if ($this->code) {
            $written['extras'] = ['php' => ['code' => true]];
        }

        return $written;
    }
}
