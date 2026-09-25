package make

import "strings"

// fill writes the template with each %NAME% placeholder replaced; %BT% is a backtick, which a raw string
// cannot hold.
func fill(template string, values ...string) string {
	pairs := append([]string{"%BT%", "`"}, values...)

	return strings.NewReplacer(pairs...).Replace(template)
}

// SkillStub is the teaching skill: only its prose is authored, the rest is projected from its sins.
func SkillStub(b Blueprint) string {
	return fill(skillTemplate,
		"%NAMESPACE%", namespace,
		"%SKILL%", b.Skill,
		"%SKILL_ID%", b.SkillID(),
		"%SLUG%", b.Slug,
		"%WORDS%", strings.ReplaceAll(b.Slug[strings.LastIndex(b.Slug, "/")+1:], "-", " "),
	)
}

// SinStub is the sin: the name a report carries and the skill it sends the reader to.
func SinStub(b Blueprint) string {
	return fill(sinTemplate, "%NAMESPACE%", namespace, "%SIN%", b.Sin, "%ID%", b.ID, "%SKILL%", reference(b.SkillClass))
}

// DetectorStub is the detector for the blueprint's engine: a selector and one rule still to write.
func DetectorStub(b Blueprint) string {
	template := map[Engine]string{Backend: backendTemplate, Frontend: frontendTemplate, Python: pythonTemplate, CSharp: csharpTemplate}[b.Engine]

	return fill(template, "%NAMESPACE%", namespace, "%SIN%", b.Sin, "%DETECTOR%", b.Detector())
}

// reference is how a generated file names a class: short when it is in the project's namespace, else
// fully qualified.
func reference(class string) string {
	at := strings.LastIndex(class, `\`)

	if at >= 0 && class[:at] == namespace {
		return class[at+1:]
	}

	return `\` + strings.TrimLeft(class, `\`)
}

const skillTemplate = `<?php

declare(strict_types=1);

namespace %NAMESPACE%;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

/**
 * TODO — one line on what discipline this skill teaches.
 *
 * Its %BT%SKILL.md%BT% is GENERATED from this class on every %BT%sync%BT%, published as
 * %BT%%SKILL_ID%%BT%. Never edit that markdown; edit this class.
 */
final class %SKILL% extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: '%SLUG%',
            tier: Tier::KeepInMind,
            order: 100,
        );
    }

    public function title(): string
    {
        return 'TODO — the H1, stated as the discipline: "%WORDS%".';
    }

    public function trigger(): string
    {
        // WHEN to load this skill, phrased as the situation — it becomes the frontmatter
        // %BT%description:%BT% an agent matches its task against.
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
`

const sinTemplate = `<?php

declare(strict_types=1);

namespace %NAMESPACE%;

use JesseGall\CodeCommandments\Sins\Sin;

/**
 * TODO — one line on the shape this sin names.
 */
final class %SIN% extends Sin
{
    public function __construct()
    {
        parent::__construct(
            // The %BT%--sin=%BT% id, and the key a report is filed against.
            name: '%ID%',
            // The skill that teaches the fix — by CLASS, so a slug rename can't strand it.
            skill: %SKILL%::class,
            // The SYMPTOM, one line: what the detector saw. ("A method returns ?Element.")
            description: 'TODO — what the detector found.',
            // The RULE, stated as a positive directive. ("Return Element::none(), never null.")
            rule: 'TODO — what to do instead.',
            // Optional: the concrete construct to reach for. Drop the line if there isn't one.
            suggestion: null,
        );
    }
}
`

const pythonTemplate = `<?php

declare(strict_types=1);

namespace %NAMESPACE%;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Finds {@see %SIN%} — TODO, one line on the shape it looks for.
 */
final class %DETECTOR% implements Detector
{
    public function sin(): Sin
    {
        return new %SIN%;
    }

    public function find(Codebase $codebase): array
    {
        // BEFORE you write a line of this: skim what the Python engine already answers —
        // %BT%whereFunction%BT%, %BT%whereClass%BT%, %BT%whereStatement%BT%, %BT%whereCall%BT%, and the node's own
        // hooks (children, expressions, descendants, variant). Load the
        // %BT%commandments-writing-detectors%BT% skill; it lists the arsenal.
        //
        // Open with a SELECTOR, then one check per line. Classify by what the tree IS —
        // never by a function or variable NAME.
        return $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => false) // TODO — the rule
            ->get();
    }
}
`

const csharpTemplate = `<?php

declare(strict_types=1);

namespace %NAMESPACE%;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Finds {@see %SIN%} — TODO, one line on the shape it looks for.
 */
final class %DETECTOR% implements Detector
{
    public function sin(): Sin
    {
        return new %SIN%;
    }

    public function find(Codebase $codebase): array
    {
        // BEFORE you write a line of this: skim what the C# engine already answers —
        // %BT%whereType%BT%, %BT%whereFunction%BT%, %BT%whereMethodDeclaration%BT%, %BT%whereStatement%BT%, %BT%whereCall%BT%,
        // and the node's own hooks (children, expressions, descendants, variant) — every type
        // resolved by Roslyn. Load the
        // %BT%commandments-writing-detectors%BT% skill; it lists the arsenal.
        //
        // Open with a SELECTOR, then one check per line. Classify by what the tree IS —
        // never by a function or variable NAME.
        return $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => false) // TODO — the rule
            ->get();
    }
}
`

const backendTemplate = `<?php

declare(strict_types=1);

namespace %NAMESPACE%;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Finds {@see %SIN%} — TODO, one line on the shape it looks for.
 */
final class %DETECTOR% implements Detector
{
    public function sin(): Sin
    {
        return new %SIN%;
    }

    public function find(Codebase $codebase): array
    {
        // BEFORE you write a line of this: skim what the engine already answers. Almost
        // every predicate you are about to hand-roll exists — %BT%AstNode%BT% alone carries ~150.
        // Load the %BT%commandments-writing-detectors%BT% skill; it lists the arsenal.
        //
        // Open with a SELECTOR, then one check per line. Classify by what the AST or the
        // resolved type IS — never by a class or method NAME.
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => false) // TODO — the rule
            ->get();
    }
}
`

const frontendTemplate = `<?php

declare(strict_types=1);

namespace %NAMESPACE%;

use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Finds {@see %SIN%} — TODO, one line on the shape it looks for.
 */
final class %DETECTOR% implements Detector
{
    public function sin(): Sin
    {
        return new %SIN%;
    }

    public function find(Codebase $components): array
    {
        // A frontend detector reads EXACTLY like a backend one: a selector opens the query,
        // %BT%where%BT%/%BT%reject%BT% narrow it one check per line. Never regex a template or a binding
        // — parse it (%BT%Vue\Expr\Parser%BT%) and query the AST.
        //
        // Load the %BT%commandments-writing-detectors%BT% skill before you write the rule.
        return $components
            ->whereElement()
            ->where(static fn (ElementMatch $element): bool => false) // TODO — the rule
            ->get();
    }
}
`
