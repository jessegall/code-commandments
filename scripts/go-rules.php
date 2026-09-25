<?php

/**
 * Writes one engine's skills and sins as Go, from the PHP declarations: skill/<engine>/ holds a type per skill with
 * its intro and principle embedded from markdown beside it, sins/<engine>/ a type per sin. Go enrols them through
 * `go generate ./registry`.
 *
 * Run from the repository root: php scripts/go-rules.php Python
 */

declare(strict_types=1);

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;
use JesseGall\CodeCommandments\Support\ClassName;

require __DIR__ . '/../vendor/autoload.php';

$engine = $argv[1] ?? '';

if ($engine === '') {
    fwrite(STDERR, "usage: php scripts/go-rules.php <Engine>\n");
    exit(2);
}

$package = strtolower($engine);
$root = dirname(__DIR__);
$label = ['CSharp' => 'C#', 'TypeScript' => 'TypeScript'][$engine] ?? $engine;

/** A Go string literal holding $text. */
$quoted = static fn (string $text): string => json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/** The Go file name of the class: `TellDontAsk` → `tell_dont_ask`. */
$file = static fn (string $class): string => strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '_', ClassName::short($class)));

/** The Go identifier a file's embedded markdown is held under: `tell_dont_ask` → `tellDontAsk`. */
$camel = static fn (string $snake): string => lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snake))));

/** The language as the Go definitions spell it. */
$language = static fn (Language $language): string => match ($language) {
    Language::Python => 'python',
    Language::CSharp => 'csharp',
    default => $language->value,
};

$skills = array_filter(Skills::all(), static fn (Skill $skill): bool => str_starts_with($skill::class, "JesseGall\\CodeCommandments\\Skills\\{$engine}\\"));
@mkdir("{$root}/skill/{$package}", 0o755, true);
file_put_contents("{$root}/skill/{$package}/doc.go", "// Package {$package} holds the {$label} skills: each teaches one discipline of {$label} code.\npackage {$package}\n");

foreach ($skills as $skill) {
    $name = ClassName::short($skill::class);
    $base = $file($skill::class);
    $held = $camel($base);
    file_put_contents("{$root}/skill/{$package}/{$base}.intro.md", $skill->intro());
    file_put_contents("{$root}/skill/{$package}/{$base}.principle.md", $skill->principle());

    $fields = [
        'Slug' => $quoted($skill->slug),
        'Tier' => $skill->tier === Tier::Mandatory ? 'skill.Mandatory' : 'skill.KeepInMind',
        'Order' => (string) $skill->order,
        'Title' => $quoted($skill->title()),
        'Trigger' => $quoted($skill->trigger()),
        'Intro' => "{$held}Intro",
        'Summary' => $quoted($skill->summary()),
        'Principle' => "{$held}Principle",
    ];

    if ($skill->examplesKeepDocblocks()) {
        $fields['ExamplesKeepDocblocks'] = 'true';
    }

    $fields['Languages'] = '[]string{' . implode(', ', array_map(static fn (Language $spoken): string => $quoted($language($spoken)), $skill->languages())) . '}';
    $related = [];

    foreach ($skill->related() as $class => $note) {
        $related[] = "\t\t\t{Slug: {$quoted((new $class())->slug)}, Note: {$quoted($note)}},";
    }

    $definition = implode("\n", array_map(static fn (string $field, string $value): string => "\t\t{$field}: {$value},", array_keys($fields), $fields));

    if ($related !== []) {
        $definition .= "\n\t\tRelated: []skill.Relation{\n" . implode("\n", $related) . "\n\t\t},";
    }

    file_put_contents("{$root}/skill/{$package}/{$base}.go", <<<GO
        package {$package}

        import (
        	_ "embed"

        	"github.com/jessegall/code-commandments/catalog"
        	"github.com/jessegall/code-commandments/skill"
        )

        var (
        	//go:embed {$base}.intro.md
        	{$held}Intro string
        	//go:embed {$base}.principle.md
        	{$held}Principle string
        )

        // {$name} teaches: {$skill->summary()}
        type {$name} struct{}

        func init() {
        	skill.Register(catalog.{$engine}, {$name}{})
        }

        // Definition is what the skill states about itself.
        func ({$name}) Definition() skill.Definition {
        	return skill.Definition{
        {$definition}
        	}
        }

        GO);
}

$sins = Sins::{$package}();
@mkdir("{$root}/sins/{$package}", 0o755, true);
file_put_contents("{$root}/sins/{$package}/doc.go", "// Package {$package} holds the {$label} sins: each names one thing wrong with {$label} code and the skill that fixes it.\npackage {$package}\n");

foreach ($sins as $sin) {
    $name = ClassName::short($sin::class);
    $taught = ClassName::short($sin->skillClass());
    $said = lcfirst(rtrim($sin->description, '.')) . '.';

    file_put_contents("{$root}/sins/{$package}/{$file($sin::class)}.go", <<<GO
        package {$package}

        import (
        	"github.com/jessegall/code-commandments/catalog"
        	"github.com/jessegall/code-commandments/sins"
        	skills "github.com/jessegall/code-commandments/skill/{$package}"
        )

        // {$name} is {$said}
        type {$name} struct{}

        func init() {
        	sins.Register(catalog.{$engine}, {$name}{})
        }

        // Definition is what the sin states about itself.
        func ({$name}) Definition() sins.Definition {
        	return sins.Definition{
        		Name: {$quoted($sin->name)},
        		Skill: skills.{$taught}{},
        		Description: {$quoted($sin->description)},
        		Rule: {$quoted($sin->rule)},
        		Suggestion: {$quoted($sin->suggestion)},
        	}
        }

        GO);
}

passthru('gofmt -w ' . escapeshellarg("{$root}/skill/{$package}") . ' ' . escapeshellarg("{$root}/sins/{$package}"));
