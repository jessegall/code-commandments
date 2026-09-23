<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

use Composer\InstalledVersions;
use JesseGall\CodeCommandments\Support\FileTree;

/**
 * A package ecosystem a sin may require a package from, and how each one tells whether the project
 * being judged has it. Every case falls back to "present" when its manifest cannot be read, so an
 * unknown environment never over-filters a rule.
 */
enum Ecosystem: string
{
    case Composer = 'composer';
    case Npm = 'npm';
    case Pip = 'pip';
    case NuGet = 'nuget';

    /**
     * Does the project at $root depend on $package, named as this ecosystem names it?
     */
    public function hasInstalled(string $package, string $root): bool
    {
        return match ($this) {
            self::Composer => ! class_exists(InstalledVersions::class) || InstalledVersions::isInstalled($package),
            self::Npm => self::inPackageJson($package, "{$root}/package.json"),
            self::Pip => self::inPythonManifests($package, $root),
            self::NuGet => self::inProjectFiles($package, $root),
        };
    }

    private static function inPackageJson(string $package, string $manifest): bool
    {
        if (! is_file($manifest)) {
            return true;
        }

        $json = (array) json_decode((string) file_get_contents($manifest), true);

        return array_key_exists($package, [...(array) ($json['dependencies'] ?? []), ...(array) ($json['devDependencies'] ?? [])]);
    }

    /**
     * $package among the `<PackageReference>`s of every project file under $root and of the `.props`
     * files MSBuild imports into them — read as XML whatever namespace an old-style project declares,
     * and compared case-blind, as NuGet compares ids.
     */
    private static function inProjectFiles(string $package, string $root): bool
    {
        $projects = [...FileTree::filesIn($root, 'csproj'), ...FileTree::filesIn($root, 'props')];

        if ($projects === []) {
            return true;
        }

        $references = [];

        foreach ($projects as $project) {
            $xml = simplexml_load_file($project, options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            if ($xml === false) {
                return true;
            }

            foreach ($xml->xpath('//*[local-name()="PackageReference"]/@Include') ?: [] as $include) {
                $references[] = strtolower((string) $include);
            }
        }

        return in_array(strtolower($package), $references, true);
    }

    /**
     * $package among the requirements files and the pyproject at $root — names compared the way pip
     * compares them, so `Typing_Extensions` is `typing-extensions`.
     */
    private static function inPythonManifests(string $package, string $root): bool
    {
        $requirements = glob("{$root}/requirements*.txt") ?: [];
        $pyproject = "{$root}/pyproject.toml";

        if ($requirements === [] && ! is_file($pyproject)) {
            return true;
        }

        $names = is_file($pyproject) ? self::pyprojectNames((string) file_get_contents($pyproject)) : [];

        foreach ($requirements as $file) {
            foreach (explode("\n", (string) file_get_contents($file)) as $line) {
                $names[] = self::requirementName($line);
            }
        }

        return in_array(self::normalised($package), array_map(self::normalised(...), array_filter($names)), true);
    }

    /**
     * The package names a pyproject depends on: the requirement strings of `[project]`'s dependency
     * arrays, and the keys of a Poetry dependencies table (bar `python`, the interpreter itself).
     *
     * @return list<string>
     */
    private static function pyprojectNames(string $toml): array
    {
        $names = [];
        $table = '';
        $inArray = false;

        foreach (explode("\n", $toml) as $raw) {
            $line = trim($raw);

            if (str_starts_with($line, '[') && ! $inArray) {
                $table = trim($line, '[] ');

                continue;
            }

            if (str_starts_with($table, 'tool.poetry') && str_ends_with($table, 'dependencies') && str_contains($line, '=')) {
                $key = trim(strstr($line, '=', true));
                $names[] = $key === 'python' ? '' : $key;

                continue;
            }

            if (str_starts_with($table, 'project') && str_contains($line, '= [')) {
                $inArray = true;
                $line = substr($line, strpos($line, '[') + 1);
            }

            if ($inArray) {
                foreach (explode(',', $line) as $item) {
                    $names[] = self::requirementName(trim($item, " \t\"'[]"));
                }

                $inArray = ! str_contains($line, ']');
            }
        }

        return $names;
    }

    /**
     * The package a requirement line names — `requests[security]==2.31 ; …` names `requests`. Empty
     * for a blank line, a comment or a pip option.
     */
    private static function requirementName(string $line): string
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '-')) {
            return '';
        }

        return substr($line, 0, strcspn($line, " <>=!~;[@"));
    }

    private static function normalised(string $name): string
    {
        $name = str_replace(['_', '.'], '-', strtolower($name));

        while (str_contains($name, '--')) {
            $name = str_replace('--', '-', $name);
        }

        return $name;
    }
}
