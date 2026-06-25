<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Caching;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;

/**
 * Serialises a file's per-prophet judgments to a JSON-able array and back, so
 * the findings cache can persist them. Sin/Warning are plain readonly value
 * objects, so the round-trip is field-for-field.
 */
final class JudgmentCodec
{
    /**
     * @param  Collection<string, Judgment>  $fileResults  prophet class => Judgment
     * @return array<string, array{sins: list<array<string, mixed>>, warnings: list<array<string, mixed>>, skipped: bool, skipReason: ?string}>
     */
    public static function encode(Collection $fileResults): array
    {
        $out = [];

        foreach ($fileResults as $prophetClass => $judgment) {
            $out[$prophetClass] = [
                'sins' => array_map(static fn (Sin $s): array => [
                    'message' => $s->message,
                    'line' => $s->line,
                    'column' => $s->column,
                    'snippet' => $s->snippet,
                    'suggestion' => $s->suggestion,
                    'symbol' => $s->symbol,
                    'autoFixable' => $s->autoFixable,
                ], array_values($judgment->sins)),
                'warnings' => array_map(static fn (Warning $w): array => [
                    'message' => $w->message,
                    'line' => $w->line,
                    'snippet' => $w->snippet,
                    'symbol' => $w->symbol,
                    'autoFixable' => $w->autoFixable,
                ], array_values($judgment->warnings)),
                'skipped' => $judgment->skipped,
                'skipReason' => $judgment->skipReason,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $encoded
     * @return Collection<string, Judgment>
     */
    public static function decode(array $encoded): Collection
    {
        $results = new Collection;

        foreach ($encoded as $prophetClass => $data) {
            $sins = array_map(static fn (array $s): Sin => new Sin(
                $s['message'],
                $s['line'] ?? null,
                $s['column'] ?? null,
                $s['snippet'] ?? null,
                $s['suggestion'] ?? null,
                $s['symbol'] ?? null,
                $s['autoFixable'] ?? null,
            ), $data['sins'] ?? []);

            $warnings = array_map(static fn (array $w): Warning => new Warning(
                $w['message'],
                $w['line'] ?? null,
                $w['snippet'] ?? null,
                $w['symbol'] ?? null,
                $w['autoFixable'] ?? null,
            ), $data['warnings'] ?? []);

            $results->put($prophetClass, new Judgment(
                sins: $sins,
                warnings: $warnings,
                skipped: $data['skipped'] ?? false,
                skipReason: $data['skipReason'] ?? null,
            ));
        }

        return $results;
    }
}
