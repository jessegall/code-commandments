<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Packages\Package;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Skill;
use ReflectionClass;

/**
 * The PROJECT's own commandments — the classes it wrote into `.commandments/custom/`
 * ({@see Workspace::CUSTOM}), discovered BY FILE because the folder is not PSR-4 mapped. Nothing
 * here auto-RUNS: a detector earns its place in `$config->detector(...)`.
 */
final class Custom
{
    /**
     * @var array<string, list<class-string>>  a SNAPSHOT of the folder => the classes it declared
     */
    private static array $loaded = [];

    /**
     * The project's own {@see Skill} classes — each published into the project's skill library on
     * sync, exactly like a shipped one.
     *
     * @return list<Skill>
     */
    public static function skills(?string $dir = null): array
    {
        return self::instancesOf(Skill::class, $dir);
    }

    /**
     * The project's own {@see Agents\Agent} classes — an assistant it wires itself into that we do
     * not ship. Published into exactly like a shipped one.
     *
     * @return list<Agents\Agent>
     */
    public static function agents(?string $dir = null): array
    {
        return self::instancesOf(Agents\Agent::class, $dir);
    }

    /**
     * The project's own {@see Sin} classes — the rows a custom skill's generated "when it fires"
     * table projects from.
     *
     * @return list<Sin>
     */
    public static function sins(?string $dir = null): array
    {
        return self::instancesOf(Sin::class, $dir);
    }

    /**
     * The project's own {@see Detector} classes. Discovered for reporting and scaffolding; a
     * detector only RUNS once `$config->detector(...)` names it.
     *
     * @return list<Detector>
     */
    public static function detectors(?string $dir = null): array
    {
        return self::instancesOf(Detector::class, $dir);
    }

    /**
     * The project's own {@see Package} classes (exemption registries).
     *
     * @return list<Package>
     */
    public static function packages(?string $dir = null): array
    {
        return self::instancesOf(Package::class, $dir);
    }

    /**
     * Is $class one the PROJECT wrote — its file living under the custom folder? The one ownership
     * test, so every surface that names a rule can say WHOSE it is: a finding from a project-local
     * detector is fixed (or reported) HERE, never upstream, and nothing in the output should leave a
     * reader guessing which of the two it is (#414).
     *
     * @param  object|class-string  $class
     */
    public static function owns(object|string $class, ?string $dir = null): bool
    {
        $file = new ReflectionClass($class)->getFileName();
        $root = realpath(Workspace::custom($dir)); // Both sides RESOLVED: reflection reports the real
        // path, so a symlinked project root would otherwise disown its own rules.

        return $file !== false && $root !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * Every instantiable class the project's custom folder declared that is a $type.
     *
     * @template T of object
     *
     * @param  class-string<T>  $type
     * @return list<T>
     */
    private static function instancesOf(string $type, ?string $dir): array
    {
        $instances = [];

        foreach (self::classes($dir) as $class) {
            if (is_subclass_of($class, $type) && new ReflectionClass($class)->isInstantiable()) {
                $instances[] = new $class;
            }
        }

        usort($instances, static fn (object $a, object $b): int => $a::class <=> $b::class);

        return $instances;
    }

    /**
     * The classes the project's custom files declare — required once per project, then remembered.
     * The declaration list is diffed across the require, so only what THESE files brought in counts
     * (a class the package or the app already had is never mistaken for the project's own).
     *
     * @return list<class-string>
     */
    private static function classes(?string $dir): array
    {
        $root = Workspace::custom($dir);
        $files = Workspace::customFiles($dir);
        $key = self::snapshot($root, $files);

        if (isset(self::$loaded[$key])) {
            return self::$loaded[$key];
        }

        $before = get_declared_classes();

        foreach ($files as $file) {
            require_once $file;
        }

        // Ownership is BY FILE, always. Diffing `get_declared_classes()` sees only what THIS
        // require added, so a folder read a second time — after a rule was scaffolded, or in a test
        // that just wrote one — would report the new class and LOSE the ones an earlier read had
        // already loaded. The folder is the test of ownership either way.
        return self::$loaded[$key] = self::declaredUnder($root);
    }

    /**
     * What the folder HELD when it was read — its path, and every file's name, size and mtime. The
     * memo is keyed by this rather than by the root, so a rule scaffolded since the last read is
     * simply a different key and discovers itself. Nothing has to remember to invalidate it, which
     * is what the `forget()` this replaces was for: a long-lived process that had just written a
     * new file, and every test that wrote one.
     *
     * @param  list<string>  $files
     */
    private static function snapshot(string $root, array $files): string
    {
        $stamps = array_map(
            static fn (string $file): string => $file . ':' . (string) @filesize($file) . ':' . (string) @filemtime($file),
            $files,
        );

        return $root . "\0" . implode("\0", $stamps);
    }

    /**
     * Every loaded class whose file lives under $root — the by-file ownership test.
     *
     * @return list<class-string>
     */
    private static function declaredUnder(string $root): array
    {
        // Both sides RESOLVED, exactly as {@see owns} does it: reflection reports a class's REAL
        // path, so an unresolved root (a symlinked project, or a macOS temp dir under /var) would
        // match nothing and the folder would look empty.
        $resolved = realpath($root);

        if ($resolved === false) {
            return [];
        }

        $classes = [];

        foreach (get_declared_classes() as $class) {
            $file = new ReflectionClass($class)->getFileName();

            if ($file !== false && str_starts_with($file, $resolved . DIRECTORY_SEPARATOR)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
