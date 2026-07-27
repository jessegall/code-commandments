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
     * @var array<string, list<class-string>>  project root => the classes its custom folder declared
     */
    private static array $loaded = [];

    /**
     * The project's own {@see Skill} classes — each published to `.claude/skills/commandments-<slug>/`
     * on sync, exactly like a shipped one.
     *
     * @return list<Skill>
     */
    public static function skills(?string $dir = null): array
    {
        return self::instancesOf(Skill::class, $dir);
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
     * Forget what a project's folder declared, so the next read discovers it afresh — for tests and
     * for a long-lived process that has just scaffolded a new file.
     */
    public static function forget(): void
    {
        self::$loaded = [];
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

        if (isset(self::$loaded[$root])) {
            return self::$loaded[$root];
        }

        $before = get_declared_classes();

        foreach (Workspace::customFiles($dir) as $file) {
            require_once $file;
        }

        $declared = array_values(array_diff(get_declared_classes(), $before));

        // A file already required by an earlier `Config::load()` in this process declares nothing
        // new, so fall back to matching every loaded class BY ITS FILE — the folder is the test of
        // ownership either way, and this keeps a second read from coming back empty.
        return self::$loaded[$root] = $declared === [] ? self::declaredUnder($root) : $declared;
    }

    /**
     * Every loaded class whose file lives under $root — the by-file ownership test.
     *
     * @return list<class-string>
     */
    private static function declaredUnder(string $root): array
    {
        $classes = [];

        foreach (get_declared_classes() as $class) {
            $file = new ReflectionClass($class)->getFileName();

            if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
