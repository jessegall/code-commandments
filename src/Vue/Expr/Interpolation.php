<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

/**
 * Pulls the `{{ … }}` expressions out of a run of template text — a delimiter scan
 * (template syntax), not a regex over the JavaScript inside. Each captured body is
 * handed to {@see Parser} for the real parse.
 */
final class Interpolation
{
    /**
     * @return list<string>  the raw expression bodies (without the braces)
     */
    public static function extract(string $text): array
    {
        $expressions = [];
        $length = strlen($text);
        $i = 0;

        while (($open = strpos($text, '{{', $i)) !== false) {
            $close = strpos($text, '}}', $open + 2);

            if ($close === false) {
                break;
            }

            $body = trim(substr($text, $open + 2, $close - ($open + 2)));

            if ($body !== '') {
                $expressions[] = $body;
            }

            $i = $close + 2;
        }

        return $expressions;
    }
}
