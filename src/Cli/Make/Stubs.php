<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Make;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Engine;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * The source `make` writes — one renderer per class a project's own commandment is made of (its
 * {@see Skill}, its {@see Sin}, its {@see Detector}). Each stub is REAL, runnable code with one hole
 * in it: the skill's prose, and the detector's `where()` chain. The stubs compose the fluent DSL
 * rather than hand over a bare `find()`, because the first line an author reads is the one they copy.
 */
final class Stubs
{
    /**
     * The teaching skill — the source of truth for what good looks like, and what a finding sends
     * the agent to read. Its enumerable sections (rules, examples, checklist) are projected from
     * its sins on `sync`, so only the prose is authored here.
     */
    public static function skill(Blueprint $blueprint): string
    {
        $namespace = Blueprint::NAMESPACE;
        $skill = (string) $blueprint->skill;
        $base = ClassName::short(Skill::class);
        $words = str_replace('-', ' ', explode('/', $blueprint->slug)[count(explode('/', $blueprint->slug)) - 1]);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use JesseGall\\CodeCommandments\\Skills\\Skill;
        use JesseGall\\CodeCommandments\\Skills\\Tier;

        /**
         * TODO — one line on what discipline this skill teaches.
         *
         * Its `SKILL.md` is GENERATED from this class on every `sync`, published as
         * `{$blueprint->skillId()}`. Never edit that markdown; edit this class.
         */
        final class {$skill} extends {$base}
        {
            public function __construct()
            {
                parent::__construct(
                    slug: '{$blueprint->slug}',
                    tier: Tier::KeepInMind,
                    order: 100,
                );
            }

            public function title(): string
            {
                return 'TODO — the H1, stated as the discipline: "{$words}".';
            }

            public function trigger(): string
            {
                // WHEN to load this skill, phrased as the situation — it becomes the frontmatter
                // `description:` an agent matches its task against.
                return 'TODO — Read this when …';
            }

            public function intro(): string
            {
                return 'TODO — one punchy sentence: the rule, stated positively.';
            }

            public function summary(): string
            {
                // The one-liner in the project briefing. Lowercase, no trailing period needed.
                return 'TODO — the rule in half a line.';
            }

            public function principle(): string
            {
                return <<<'PRINCIPLE'
                TODO — the conceptual WHY, in prose. No rule list and no code examples: those are
                projected from this skill's sins, so writing them here would only let them drift.

                Explain what goes wrong when the discipline is broken, and what the reader should
                see instead. This is the part a human actually reads.
                PRINCIPLE;
            }
        }

        PHP;
    }

    /**
     * The sin — the name a report carries, the skill it sends the reader to, and the wording the
     * generated docs project from.
     */
    public static function sin(Blueprint $blueprint): string
    {
        $namespace = Blueprint::NAMESPACE;
        $base = ClassName::short(Sin::class);
        $skill = self::reference($blueprint->skillClass, $namespace);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use JesseGall\\CodeCommandments\\Sins\\Sin;

        /**
         * TODO — one line on the shape this sin names.
         */
        final class {$blueprint->sin} extends {$base}
        {
            public function __construct()
            {
                parent::__construct(
                    // The `--sin=` id, and the key a report is filed against.
                    name: '{$blueprint->id}',
                    // The skill that teaches the fix — by CLASS, so a slug rename can't strand it.
                    skill: {$skill}::class,
                    // The SYMPTOM, one line: what the detector saw. ("A method returns ?Element.")
                    description: 'TODO — what the detector found.',
                    // The RULE, stated as a positive directive. ("Return Element::none(), never null.")
                    rule: 'TODO — what to do instead.',
                    // Optional: the concrete construct to reach for. Drop the line if there isn't one.
                    suggestion: null,
                );
            }
        }

        PHP;
    }

    /**
     * The detector — the finder. Its `find()` is the one genuine hole: a selector to open the
     * query, then one `where()`/`reject()` per check.
     */
    public static function detector(Blueprint $blueprint): string
    {
        return $blueprint->engine === Engine::Backend
            ? self::backendDetector($blueprint)
            : self::frontendDetector($blueprint);
    }

    private static function backendDetector(Blueprint $blueprint): string
    {
        $namespace = Blueprint::NAMESPACE;

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use JesseGall\\CodeCommandments\\Ast\\AstNode;
        use JesseGall\\CodeCommandments\\Ast\\Codebase;
        use JesseGall\\CodeCommandments\\Backend\\Detector;
        use JesseGall\\CodeCommandments\\Sins\\Sin;

        /**
         * Finds {@see {$blueprint->sin}} — TODO, one line on the shape it looks for.
         */
        final class {$blueprint->detector()} implements Detector
        {
            public function sin(): Sin
            {
                return new {$blueprint->sin};
            }

            public function find(Codebase \$codebase): array
            {
                // BEFORE you write a line of this: skim what the engine already answers. Almost
                // every predicate you are about to hand-roll exists — `AstNode` alone carries ~150.
                // Load the `commandments-writing-detectors` skill; it lists the arsenal.
                //
                // Open with a SELECTOR, then one check per line. Classify by what the AST or the
                // resolved type IS — never by a class or method NAME.
                return \$codebase
                    ->whereMethodDeclaration()
                    ->where(static fn (AstNode \$node): bool => false) // TODO — the rule
                    ->get();
            }
        }

        PHP;
    }

    private static function frontendDetector(Blueprint $blueprint): string
    {
        $namespace = Blueprint::NAMESPACE;

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use JesseGall\\CodeCommandments\\Frontend\\Detector;
        use JesseGall\\CodeCommandments\\Sins\\Sin;
        use JesseGall\\CodeCommandments\\Vue\\Codebase;
        use JesseGall\\CodeCommandments\\Vue\\ElementMatch;

        /**
         * Finds {@see {$blueprint->sin}} — TODO, one line on the shape it looks for.
         */
        final class {$blueprint->detector()} implements Detector
        {
            public function sin(): Sin
            {
                return new {$blueprint->sin};
            }

            public function find(Codebase \$components): array
            {
                // A frontend detector reads EXACTLY like a backend one: a selector opens the query,
                // `where`/`reject` narrow it one check per line. Never regex a template or a binding
                // — parse it (`Vue\\Expr\\Parser`) and query the AST.
                //
                // Load the `commandments-writing-detectors` skill before you write the rule.
                return \$components
                    ->whereElement()
                    ->where(static fn (ElementMatch \$element): bool => false) // TODO — the rule
                    ->get();
            }
        }

        PHP;
    }

    /**
     * How a generated file refers to a class: by its short name when it lives in the same
     * namespace, else fully qualified. So a sin pointing at the project's own skill reads
     * `Absence::class`, and one pointing at a shipped skill carries the whole path.
     */
    private static function reference(string $fqcn, string $namespace): string
    {
        return ClassName::namespace($fqcn) === $namespace
            ? ClassName::short($fqcn)
            : '\\' . ltrim($fqcn, '\\');
    }
}
