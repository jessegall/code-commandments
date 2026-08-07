<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Make;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Support\Name;

/**
 * ONE commandment a project is about to write, resolved: the names, the slugs, the file paths and
 * the namespace, all derived from the single word the human typed. It answers what to CALL things;
 * {@see Stubs} answers what to put IN them and {@see Make} does the writing — so the naming rules
 * (a sin is `Foo`, its detector is `FooDetector`, its id is `foo`) are stated once and every
 * generated file, the config line and the printed guidance all read them from here.
 */
final class Blueprint
{
    /**
     * The namespace a project's own commandments declare. The `custom/` folder is not PSR-4 mapped
     * — {@see \JesseGall\CodeCommandments\Custom} requires the files directly — so the namespace is
     * free; a fixed one keeps the generated classes cross-referencing each other cleanly and keeps
     * them clear of the app's own tree.
     */
    public const string NAMESPACE = 'Commandments';

    private function __construct(
        /**
         * The sin's class name, StudlyCase — `NullableElementReturn`.
         */
        public readonly string $sin,
        /**
         * The `--sin=` id a report carries — `nullable-element-return`.
         */
        public readonly string $id,
        /**
         * Which engine the detector reads: the PHP AST, or the Vue components.
         */
        public readonly Engine $engine,
        /**
         * The skill slug the sin points at — the existing one named on the command line, or the
         * one this blueprint is about to create.
         */
        public readonly string $slug,
        /**
         * The teaching skill's class name, or null when the sin points at a skill that already
         * exists (shipped or custom) and no new one is written.
         */
        public readonly ?string $skill,
        /**
         * The FQCN of the skill the sin references — the class it is about to create, or the
         * existing one it was pointed at.
         */
        public readonly string $skillClass,
        /**
         * Where the project's custom folder is.
         */
        public readonly string $dir,
    ) {}

    /**
     * Resolve what `make <name>` is going to produce. $skillClass is the FQCN of an EXISTING skill
     * when one was matched, in which case no skill file is written; otherwise a project skill is
     * created for $slug.
     */
    public static function of(string $name, Engine $engine, string $slug, ?string $skillClass, string $dir): self
    {
        $sin = Name::studly(self::withoutDetectorSuffix($name));
        $skill = $skillClass === null ? self::skillNamed(self::stem($slug), $sin) : null;

        return new self(
            sin: $sin,
            id: Name::kebab($sin),
            engine: $engine,
            slug: $slug,
            skill: $skill,
            skillClass: $skillClass ?? self::NAMESPACE . '\\' . $skill,
            dir: $dir,
        );
    }

    /**
     * The detector's class name — the sin's, suffixed. The suffix is the convention every report
     * and `--detector=` lookup already speaks.
     */
    public function detector(): string
    {
        return "{$this->sin}Detector";
    }

    /**
     * The detector's FQCN — what the config's `->detector(...)` line names.
     */
    public function detectorClass(): string
    {
        return self::NAMESPACE . '\\' . $this->detector();
    }

    /**
     * The files this blueprint writes: absolute path => the class it declares. A skill file is
     * absent when the sin points at one that already exists.
     *
     * @return array<string, string>  path => short class name
     */
    public function files(): array
    {
        $files = [
            "{$this->dir}/{$this->sin}.php" => $this->sin,
            "{$this->dir}/{$this->detector()}.php" => $this->detector(),
        ];

        if ($this->skill !== null) {
            $files = ["{$this->dir}/{$this->skill}.php" => $this->skill] + $files;
        }

        return $files;
    }

    /**
     * The skill's loadable id — the name an agent loads it by once `sync` has published it. One
     * home for the flatten ({@see Skill::idFor}), because the id is also the published DIRECTORY
     * name on both sides of a link: drift leaves an agent pointed at nothing.
     */
    public function skillId(): string
    {
        return Skill::idFor($this->slug);
    }

    /**
     * What to CALL a new skill, given the slug it teaches and the sin that points at it. Normally
     * the slug's own words (`absence` → `Absence`), but a skill named for its only sin would want
     * the sin's very class name — so it is suffixed instead of silently taking the same file.
     *
     * That collision is what happens when no `--skill` is given at all: the slug is derived from
     * the sin, so the two names meet. The suffix keeps both classes real, and reads as the prompt it
     * is — a skill named `FooSkill` is asking to be renamed to the discipline it actually teaches.
     */
    private static function skillNamed(string $stem, string $sin): string
    {
        $skill = Name::studly($stem);

        return $skill === $sin ? "{$skill}Skill" : $skill;
    }

    /**
     * The last segment of a slug — `backend/absence` → `absence` — the words a generated skill
     * class is named after.
     */
    private static function stem(string $slug): string
    {
        $parts = explode('/', $slug);

        return end($parts);
    }

    /**
     * `NullableElementReturnDetector` typed as the name is the SAME commandment as
     * `NullableElementReturn` — the sin is the thing being named, so the suffix is dropped.
     */
    private static function withoutDetectorSuffix(string $name): string
    {
        $studly = Name::studly($name);

        return str_ends_with($studly, 'Detector') ? substr($studly, 0, -strlen('Detector')) : $studly;
    }
}
