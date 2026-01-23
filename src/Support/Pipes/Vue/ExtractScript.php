<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Extract the script section from a Vue SFC.
 *
 * @implements Pipe<VueContext, VueContext>
 */
final class ExtractScript implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $script = $this->extractScriptSection($input->content);

        return $input->with(script: $script);
    }

    /**
     * Extract the script section.
     *
     * @return array{content: string, start: int, end: int, lang: string|null, setup: bool}|null
     */
    private function extractScriptSection(string $content): ?array
    {
        if (! preg_match('/<script(\s+[^>]*)?>(.+?)<\/script>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $attributes = $matches[1][0] ?? '';
        $scriptContent = $matches[2][0];
        $start = $matches[2][1];

        $lang = null;
        if (preg_match('/lang=["\'](\w+)["\']/', $attributes, $langMatch)) {
            $lang = $langMatch[1];
        }

        return [
            'content' => $scriptContent,
            'start' => $start,
            'end' => $start + strlen($scriptContent),
            'lang' => $lang,
            'setup' => str_contains($attributes, 'setup'),
        ];
    }
}
