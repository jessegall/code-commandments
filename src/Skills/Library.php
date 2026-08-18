<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\LanguageSections;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * Writes the skills into the project's library — the one place they really live, which every agent
 * then reads directly or through a link. It keeps a manifest of what it published, which is what
 * lets it clean up after itself in a folder it shares with the project ({@see reconcile}).
 */
final class Library
{
    /**
     * The hand-written process skills — not the teaching skills projected from sins, but documents
     * that ship as they are.
     */
    private const array STANDALONE = ['executing-plans', 'stop-condition', 'writing-detectors'];

    private const string MANIFEST = 'published-skills';

    /**
     * @var list<string>
     */
    private readonly array $previous;

    public function __construct(
        private readonly Workspace $workspace,
        private readonly Languages $languages = new Languages(),
    ) {
        $this->previous = $this->readManifest();
    }

    public function dir(): string
    {
        return $this->workspace->library();
    }

    public function path(string $id): string
    {
        return $this->dir() . '/' . $id;
    }

    /**
     * Strip the examples of languages this project does not write from a skill just copied in. A
     * SHIPPED skill is copied rather than re-rendered — its examples come from the package's own
     * fixtures, which a consumer does not have — so the filtering happens on the rendered document.
     */
    private function keepWrittenLanguages(string $file): void
    {
        if ($this->languages->disabled() === [] || ! is_file($file)) {
            return;
        }

        File::write($file, LanguageSections::keep((string) file_get_contents($file), $this->languages));
    }

    /**
     * Publish every skill and record what was published. Returns the ids, in the order an agent
     * should be pointed at them.
     *
     * @return list<string>
     */
    public function publish(string $packageRoot): array
    {
        $ids = [];

        // The map itself, published like any other skill and named FIRST — an agent that needs to
        // know WHICH discipline covers a subject loads it the same way it loads a discipline.
        @mkdir($this->path(Router::ID), 0775, true);

        if (File::write($this->path(Router::ID) . '/SKILL.md', Router::render($this->workspace->root(), $this->languages))) {
            $ids[] = Router::ID;
        }

        foreach (Catalog::all() as $skill) {
            $source = "{$packageRoot}/skills/commandments/{$skill->slug}";

            if (is_dir($source) && Directory::copy($source, $this->path($skill->id()))) {
                $this->keepWrittenLanguages($this->path($skill->id()) . '/SKILL.md');
                $ids[] = $skill->id();
            }
        }

        foreach (self::STANDALONE as $slug) {
            $source = "{$packageRoot}/skills/{$slug}";

            if (is_dir($source) && Directory::copy($source, $this->path(Skill::idFor($slug)))) {
                $ids[] = Skill::idFor($slug);
            }
        }

        foreach (Custom::skills($this->workspace->root()) as $skill) {
            $renderer = new SkillRenderer($this->languages);
            $written = false;

            foreach ($renderer->documents($skill) as $relative => $document) {
                @mkdir(dirname($this->path($skill->id()) . '/' . $relative), 0775, true);
                $written = File::write($this->path($skill->id()) . '/' . $relative, $document) || $written;
            }

            if ($written) {
                $ids[] = $skill->id();
            }
        }

        $this->reconcile($this->dir(), $ids);
        $this->writeManifest($ids);

        return $ids;
    }

    /**
     * Remove from $dir every skill we published LAST time and are not publishing now — one that was
     * renamed, retired, or whose class a project deleted from its own rules.
     *
     * Only ever those. The library is a shared folder: a project may keep skills of its own beside
     * ours, and one of them may perfectly well be called `commandments-something`. Sweeping the
     * folder by name would take it, so what we did not put there is left exactly where it is.
     *
     * @param  list<string>  $current
     */
    public function reconcile(string $dir, array $current): void
    {
        foreach (array_diff($this->previous, $current) as $stale) {
            if (file_exists("{$dir}/{$stale}") || is_link("{$dir}/{$stale}")) {
                Directory::delete("{$dir}/{$stale}");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function readManifest(): array
    {
        $path = $this->workspace->shared(self::MANIFEST);
        $lines = is_file($path) ? explode("\n", (string) file_get_contents($path)) : [];

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));
    }

    /**
     * @param  list<string>  $ids
     */
    private function writeManifest(array $ids): void
    {
        @mkdir(dirname($this->workspace->shared(self::MANIFEST)), 0775, true);

        File::write(
            $this->workspace->shared(self::MANIFEST),
            "# The skills code-commandments published here, so it can retire its own and leave yours\n"
            . "# alone. Regenerated on every sync; deleting it only means a retired skill lingers.\n"
            . implode("\n", $ids) . "\n",
        );
    }
}
