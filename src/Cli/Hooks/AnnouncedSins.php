<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use Closure;
use JesseGall\CodeCommandments\Hooks\SinMark;

/**
 * The sins the plugin has announced, per file and by each sin's identity — so a sin is found once, and
 * called repented only when the file it was in is edited again and, judged whole, no longer holds it.
 */
final class AnnouncedSins
{
    /**
     * @param  array<string, array<string, string>>  $announced  file => sin id => how the sin was shown
     * @param  Closure(array<string, array<string, string>>): void  $keep
     */
    private function __construct(
        private array $announced,
        private readonly Closure $keep,
    ) {}

    /**
     * What was announced before, kept in $folder across moments.
     */
    public static function keptIn(string $folder): self
    {
        $path = "{$folder}/sins.json";
        $read = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];

        // An older record listed each file's sins without their identity; it proves nothing, so it starts over.
        $announced = array_filter($read, static fn (mixed $sins): bool => is_array($sins) && ! array_is_list($sins));

        return new self($announced, static fn (array $announced) => file_put_contents($path, json_encode(array_filter($announced), JSON_UNESCAPED_SLASHES)));
    }

    /**
     * Nothing announced before and nothing kept after — every sin on a changed line is news.
     */
    public static function forgotten(): self
    {
        return new self([], static function (): void {});
    }

    /**
     * What this moment changes: found are the sins on changed lines not announced before, resolved the sins
     * announced in $edited — the file the moment's tool wrote, judged whole — that it no longer holds. Any
     * other file keeps what was announced for it.
     *
     * @param  list<SinMark>  $marks  every sin the judged files hold now
     */
    public function settle(string $root, ?string $edited, array $marks): Settlement
    {
        $now = [];

        foreach ($marks as $mark) {
            $now[self::relative($root, $mark->match->file())][$mark->id()] = $mark;
        }

        $found = [];
        $resolved = [];
        $judged = $edited === null ? [] : [self::relative($root, $edited) => true];

        foreach (array_keys($now + $judged) as $file) {
            $before = $this->announced[$file] ?? [];
            $holds = $now[$file] ?? [];
            $news = array_filter($holds, static fn (SinMark $mark, string $id): bool => $mark->touched && ! isset($before[$id]), ARRAY_FILTER_USE_BOTH);
            $kept = isset($judged[$file]) ? array_intersect_key($before, $holds) : $before;

            $found = [...$found, ...array_values($news)];
            $resolved += array_diff_key($before, $kept);
            $this->announced[$file] = $kept + array_map(static fn (SinMark $mark): string => $mark->shownFrom($root), $news);
        }

        ($this->keep)($this->announced);

        return new Settlement($found, $resolved);
    }

    /**
     * $file under $root, both resolved first — the edited path and the parsed one may name it differently.
     */
    private static function relative(string $root, string $file): string
    {
        return str_replace(rtrim(realpath($root) ?: $root, '/') . '/', '', realpath($file) ?: $file);
    }
}
