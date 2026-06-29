<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Parses the raw attribute text of a tag into name => value pairs, preserving Vue's
 * directive syntax verbatim — `v-if`, `v-for`, `:title` (`v-bind`), `@click`
 * (`v-on`), `#default` (`v-slot`). A valueless attribute (`disabled`, `#default`,
 * `v-pre`) maps to null; `>` and `=` inside a quoted value are honoured.
 */
final class Attributes
{
    /**
     * @return array<string, string|null>
     */
    public static function parse(string $raw): array
    {
        $attributes = [];
        $length = strlen($raw);
        $i = 0;

        while ($i < $length) {
            while ($i < $length && ctype_space($raw[$i])) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            $start = $i;
            while ($i < $length && ! ctype_space($raw[$i]) && $raw[$i] !== '=' && $raw[$i] !== '/') {
                $i++;
            }

            $name = substr($raw, $start, $i - $start);

            if ($name === '') {
                $i++;
                continue;
            }

            while ($i < $length && ctype_space($raw[$i])) {
                $i++;
            }

            if ($i < $length && $raw[$i] === '=') {
                $i++;
                $attributes[$name] = self::readValue($raw, $i, $length);
            } else {
                $attributes[$name] = null;
            }
        }

        return $attributes;
    }

    private static function readValue(string $raw, int &$i, int $length): string
    {
        while ($i < $length && ctype_space($raw[$i])) {
            $i++;
        }

        if ($i < $length && ($raw[$i] === '"' || $raw[$i] === "'")) {
            $quote = $raw[$i++];
            $start = $i;

            while ($i < $length && $raw[$i] !== $quote) {
                $i++;
            }

            $value = substr($raw, $start, $i - $start);
            $i++; // closing quote

            return $value;
        }

        $start = $i;
        while ($i < $length && ! ctype_space($raw[$i]) && $raw[$i] !== '>') {
            $i++;
        }

        return substr($raw, $start, $i - $start);
    }
}
