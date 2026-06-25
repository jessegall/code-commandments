<?php

declare(strict_types=1);

namespace Notifications;

final class Template
{
    public function __construct(
        private string $body,
    ) {}

    /**
     * @param array<string, string> $vars
     */
    public function render(array $vars): string
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        return strtr($this->body, $replacements);
    }
}
