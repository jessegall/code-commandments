<?php

namespace Shop\Constants;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class Notices
{
    /**
     * @param  list<string>  $lines
     */
    #[Sinful(ConstantArgument::class)]
    public function join(array $lines, string $glue): string
    {
        return implode($glue, $lines);
    }

    #[Righteous(ConstantArgument::class)]
    public function tagged(string $text, string $tag): string
    {
        return "[{$tag}] {$text}";
    }

    /**
     * @param  list<string>  $lines
     */
    public function render(array $lines): string
    {
        $body = $this->join($lines, "\n");
        $title = $this->tagged($this->join(array_slice($lines, 0, 1), "\n"), 'notice');

        return $title . $this->tagged($body, 'body') . $this->tagged($this->join($lines, "\n"), 'tail');
    }
}
