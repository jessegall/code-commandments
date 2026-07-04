<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Lexes raw tag attribute text, preserving Vue directive syntax (`v-if`, `v-for`, `:title`, `@click`, `#default`).
 * Scan yields attributes with byte spans for write operations; parse returns name→value map.
 */
final class Attributes
{
    /**
     * @return array<string, string|null>  name => value (null = valueless)
     */
    public static function parse(string $raw): array
    {
        $attributes = [];

        foreach (self::scan($raw) as $attribute) {
            $attributes[$attribute['name']] = $attribute['value'];
        }

        return $attributes;
    }

    /**
     * Every attribute in source order, each with the `[start, end)` byte span (name through
     * value) it occupies in $raw — the leading whitespace is NOT included.
     *
     * @return list<array{name: string, value: string|null, start: int, end: int}>
     */
    public static function scan(string $raw): array
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

            $afterName = $i;
            while ($i < $length && ctype_space($raw[$i])) {
                $i++;
            }

            if ($i < $length && $raw[$i] === '=') {
                $i++;
                $value = self::readValue($raw, $i, $length);
                $end = $i;
            } else {
                $value = null;
                $end = $afterName;
                $i = $afterName; // a peeked space belongs to the NEXT attribute, not this one
            }

            $attributes[] = ['name' => $name, 'value' => $value, 'start' => $start, 'end' => $end];
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
