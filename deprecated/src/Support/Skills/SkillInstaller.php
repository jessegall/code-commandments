<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Skills;

/**
 * Stamps the package's skill stubs into the consumer's
 * `.claude/skills/commandments-<slug>/` tree, rewriting the example namespace
 * to the configured scaffold namespace so a skill's examples are exactly the
 * code `scaffold` generates. The literal twin of
 * {@see \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator}.
 *
 * Idempotent: an existing skill file is never overwritten unless $force is set
 * (or auto-refresh is on, which forces and stamps a do-not-edit banner). The
 * whole `SKILL.md` + `reference/` subtree is copied recursively so adding a new
 * reference deep-dive to a stub needs no code change here.
 */
final class SkillInstaller
{
    public const STATUS_CREATED = 'created';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_REWRITTEN = 'rewritten';
    public const STATUS_MISSING_STUB = 'missing_stub';

    public function __construct(
        private readonly string $stubDir,
    ) {}

    public static function packaged(): self
    {
        return new self(__DIR__ . '/../../../stubs/skills');
    }

    /**
     * Install every catalogued skill into $targetRoot (`.claude/skills`), as a
     * flat `commandments-<slug>/` dir per skill, rewriting `{{ namespace }}` to
     * $namespace.
     *
     * @param  list<string>  $except  skill slugs to skip
     * @return list<array{slug: string, status: string, files: int}>
     */
    public function install(string $namespace, string $targetRoot, bool $force = false, array $except = [], bool $autoRefresh = false): array
    {
        $namespace = trim($namespace, '\\');
        $results = [];

        // Self-heal the pre-flat layout: older versions nested every skill under a
        // single `.claude/skills/commandments/` group dir, which Claude Code never
        // discovered (it scans flat — `.claude/skills/<name>/SKILL.md`). Remove that
        // dead group dir so a re-install migrates cleanly to the flat layout (#132).
        $this->clearLegacyGroupDir($targetRoot);

        foreach (SkillRegistry::all() as $skill) {
            if (in_array($skill->slug, $except, true)) {
                continue;
            }

            $results[] = $this->writeSkill($skill, $namespace, $targetRoot, $force, $autoRefresh);
        }

        return $results;
    }

    /**
     * Remove the dead pre-flat group dir (`<root>/commandments/`) if it is exactly
     * that — a directory with no SKILL.md of its own that nests a known skill slug.
     * Guarded so it never deletes a legitimately-named flat skill.
     */
    private function clearLegacyGroupDir(string $targetRoot): void
    {
        $legacy = rtrim($targetRoot, '/') . '/commandments';

        if (! is_dir($legacy) || is_file($legacy . '/SKILL.md')) {
            return;
        }

        foreach (SkillRegistry::all() as $skill) {
            if (is_file($legacy . '/' . $skill->slug . '/SKILL.md')) {
                $this->deleteTree($legacy);

                return;
            }
        }
    }

    private function deleteTree(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    /**
     * @return array{slug: string, status: string, files: int}
     */
    private function writeSkill(Skill $skill, string $namespace, string $targetRoot, bool $force, bool $autoRefresh): array
    {
        $sourceDir = rtrim($this->stubDir, '/') . '/' . $skill->stubDir();

        if (! is_dir($sourceDir)) {
            return ['slug' => $skill->slug, 'status' => self::STATUS_MISSING_STUB, 'files' => 0];
        }

        // Flat layout: each skill is a directory DIRECTLY under .claude/skills/,
        // named for its frontmatter `name:` (commandments-<slug>) so Claude Code
        // discovers + registers it. NOT nested under a group dir (#132).
        $targetDir = rtrim($targetRoot, '/') . '/' . $skill->skillName();

        $created = 0;
        $rewritten = 0;

        foreach ($this->stubFiles($sourceDir) as $relative) {
            $status = $this->writeFile(
                $sourceDir . '/' . $relative,
                $targetDir . '/' . $relative,
                $namespace,
                $force,
                $autoRefresh,
            );

            if ($status === self::STATUS_CREATED) {
                $created++;
            } elseif ($status === self::STATUS_REWRITTEN) {
                $rewritten++;
            }
        }

        $status = match (true) {
            $created > 0 => self::STATUS_CREATED,
            $rewritten > 0 => self::STATUS_REWRITTEN,
            default => self::STATUS_SKIPPED,
        };

        return ['slug' => $skill->slug, 'status' => $status, 'files' => $created + $rewritten];
    }

    private function writeFile(string $source, string $target, string $namespace, bool $force, bool $autoRefresh): string
    {
        $exists = is_file($target);

        if ($exists && ! $force) {
            return self::STATUS_SKIPPED;
        }

        $contents = (string) file_get_contents($source);
        $contents = str_replace('{{ namespace }}', $namespace, $contents);
        $contents = $this->applyHeader($contents, $autoRefresh);

        $dir = dirname($target);

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (@file_put_contents($target, $contents) === false) {
            return self::STATUS_SKIPPED;
        }

        return $exists ? self::STATUS_REWRITTEN : self::STATUS_CREATED;
    }

    /**
     * Prepend a LOUD do-not-edit banner when auto-refresh is on — these files
     * are regenerated automatically, so any hand-edit is doomed. Mirrors
     * {@see \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator::applyHeader}.
     */
    private function applyHeader(string $contents, bool $autoRefresh): string
    {
        if (! $autoRefresh) {
            return $contents;
        }

        $banner = "<!--\n"
            . "  !!  AUTO-GENERATED — DO NOT EDIT THIS FILE  !!\n\n"
            . "  skills.auto_refresh is ON: code-commandments REGENERATES this file\n"
            . "  automatically, so ANY CHANGE YOU MAKE HERE WILL BE OVERWRITTEN. Edit the\n"
            . "  package stub instead, or set skills.auto_refresh = false to own it by hand.\n"
            . "-->\n";

        return $banner . $contents;
    }

    /**
     * Every file under a subject's stub dir, as paths relative to it (so the
     * SKILL.md and the whole reference/ tree are mirrored verbatim).
     *
     * @return list<string>
     */
    private function stubFiles(string $sourceDir): array
    {
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            );
        } catch (\Throwable) {
            return [];
        }

        $prefix = rtrim($sourceDir, '/') . '/';

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (str_starts_with($path, $prefix)) {
                $files[] = substr($path, strlen($prefix));
            }
        }

        sort($files);

        return $files;
    }
}
