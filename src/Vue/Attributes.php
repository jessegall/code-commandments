<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Lexes the raw attribute text of a tag, preserving Vue's directive syntax verbatim —
 * `v-if`, `v-for`, `:title` (`v-bind`), `@click` (`v-on`), `#default` (`v-slot`). A
 * valueless attribute (`disabled`, `#default`, `v-pre`) carries a null value; `>` and
 * `=` inside a quoted value are honoured.
 *
 * {@see scan} is the lexer — it yields every attribute WITH its `[start, end)` span in the
 * raw text, so the write engine can remove a directive by its known span (never a regex).
 * {@see parse} is the name → value view over the same scan.
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
