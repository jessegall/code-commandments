<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Extract the template section from a Vue SFC.
 *
 * @implements Pipe<VueContext, VueContext>
 */
final class ExtractTemplate implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $template = $this->extractTemplateSection($input->content);

        return $input->with(template: $template);
    }

    /**
     * Extract the template section with proper nesting handling.
     *
     * @return array{content: string, start: int, end: int}|null
     */
    private function extractTemplateSection(string $content): ?array
    {
        if (! preg_match('/<template(\s+[^>]*)?>/', $content, $openMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $openTagStart = $openMatch[0][1];
        $openTagEnd = $openTagStart + strlen($openMatch[0][0]);
        $contentStart = $openTagEnd;

        $depth = 1;
        $pos = $openTagEnd;
        $length = strlen($content);

        while ($depth > 0 && $pos < $length) {
            $nextOpen = preg_match('/<template(\s+[^>]*)?>/', $content, $m, PREG_OFFSET_CAPTURE, $pos) ? $m[0][1] : PHP_INT_MAX;
            $nextClose = preg_match('/<\/template\s*>/', $content, $m, PREG_OFFSET_CAPTURE, $pos) ? $m[0][1] : PHP_INT_MAX;

            if ($nextClose === PHP_INT_MAX) {
                return null;
            }

            if ($nextOpen < $nextClose) {
                $depth++;
                preg_match('/<template(\s+[^>]*)?>/', $content, $m, PREG_OFFSET_CAPTURE, $pos);
                $pos = $m[0][1] + strlen($m[0][0]);
            } else {
                $depth--;
                preg_match('/<\/template\s*>/', $content, $m, PREG_OFFSET_CAPTURE, $pos);
                if ($depth === 0) {
                    return [
                        'content' => substr($content, $contentStart, $m[0][1] - $contentStart),
                        'start' => $contentStart,
                        'end' => $m[0][1],
                    ];
                }
                $pos = $m[0][1] + strlen($m[0][0]);
            }
        }

        return null;
    }
}
