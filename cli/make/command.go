package make

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/binary"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/skill"
)

// Command is `make`.
type Command struct{}

func (Command) Names() []string {
	return []string{"make"}
}

func (Command) Help() help.Help {
	return help.Of("Scaffold a commandment of your own — a rule naming its sin, and the skill that teaches the fix, in `.commandments/custom/`, turned on in your config, with the rest of the process printed for you.").
		Form("make <Name>", "scaffold a PHP commandment and turn it on").
		Form("make <Name> --engine=python", "scaffold a Python one instead").
		Form("make <Name> --engine=php,python,go", "scaffold one rule that judges each engine named").
		Form("make <Name> --engine=php,vue,typescript,python,csharp,go", "scaffold one that judges every language").
		Form("make <Name> --skill=NAME", "point the sin at an EXISTING skill (shipped or your own) instead of writing a new one").
		Form("make <Name> --from=<template>", "start from a ready rule to adapt: "+templateNames()).
		Option("--engine=ENGINE[,ENGINE…]", "the engines the rule judges, one or several of php, vue, typescript, python, csharp and go (default: php)").
		Option("--skill=NAME", "the skill that teaches the fix — a lenient name/slug match against the existing skills, or a new slug to create one").
		Option("--from=<template>", "the ready rule to start from, its query written over the generic tree").
		Option("--force", "overwrite files that already exist").
		Note("A rule is data the binary runs: `<Name>Detector.json`, written in commandments:neutral-rule/v2, names " +
			"the engines it judges, its sin and one query over the generic tree — a kind to select, the name each " +
			"node is bound to, and a `where` expression it must hold. A new skill is `skills/<slug>/SKILL.md`, " +
			"published as you write it. The folder is kept OUT of the .commandments/ gitignore — your rules are " +
			"source, so commit them.").
		Note("A scaffolded rule does not work yet, and is not meant to: its query finds nothing until you write " +
			"it. Load the `commandments-writing-detectors` skill first — it lists the checks a step can make and " +
			"teaches the probe-then-calibrate discipline that proves a rule fires on what you meant.")
}

func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	name, named := in.FirstArgument()
	if !named || Studly(name) == "" {
		return help.Usage(console.Err, c, "name the commandment, e.g. `"+binary.Here()+" make NullableElementReturn`."), nil
	}

	engines := []Engine{catalog.PHP}

	if given, set := in.Option("engine"); set {
		engines = nil
		for _, name := range strings.Split(given, ",") {
			parsed, known := ParseEngine(strings.TrimSpace(name))
			if !known {
				return help.Usage(console.Err, c, "unknown engine `"+name+"` in --engine="+given+" — each is one of `php`, `vue`, `typescript`, `python`, `csharp`, `go`."), nil
			}

			engines = append(engines, parsed)
		}
	}

	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	query, _ := in.Option("skill")
	blueprint := plan(name, engines, query, root)

	if from, set := in.Option("from"); set {
		template, known := TemplateNamed(from)
		if !known {
			return help.Usage(console.Err, c, "no template `"+from+"` — the ready rules are "+templateNames()+"."), nil
		}

		for _, engine := range engines {
			if !template.Judges(engine) {
				return help.Usage(console.Err, c, "the `"+from+"` template is written for "+engineList(template.Engines())+", not "+string(engine)+"."), nil
			}
		}

		blueprint.From = &template
	}

	if clash := existing(blueprint, in.HasFlag("force")); len(clash) > 0 {
		console.Warn("Already there — pass --force to overwrite:\n  " + strings.Join(clash, "\n  "))

		return 2, nil
	}

	if err := write(blueprint, root); err != nil {
		return 0, err
	}

	registered, err := config.EditorIn(root).RegisterDetector(blueprint.Detector())
	if err != nil {
		return 0, err
	}

	report(blueprint, registered, root, console)

	return 0, nil
}

func plan(name string, engines []Engine, query, root string) Blueprint {
	dir := workspace.CustomDir(root)

	if query != "" {
		var matched []skill.Definition
		for _, teaching := range append(skill.Ordered(), ownSkills(root)...) {
			if definition := teaching.Definition(); definition.Matches(query) {
				matched = append(matched, definition)
			}
		}
		ownEngine := slices.IndexFunc(matched, func(definition skill.Definition) bool {
			return strings.HasPrefix(definition.Slug, string(engines[0])+"/")
		})
		if ownEngine >= 0 {
			return Of(name, engines, matched[ownEngine].Slug, false, dir)
		}
		if len(matched) > 0 {
			return Of(name, engines, matched[0].Slug, false, dir)
		}
	}

	blueprint := Of(name, engines, "", true, dir)
	blueprint.Slug = Kebab(blueprint.Sin)

	if query != "" {
		blueprint.Slug = Kebab(query)
	}

	return blueprint
}

func ownSkills(root string) []skill.Skill {
	var own []skill.Skill

	for _, each := range custom.Load(root).Skills {
		own = append(own, each)
	}

	return own
}

func existing(blueprint Blueprint, force bool) []string {
	var clash []string

	for _, file := range blueprint.Files() {
		if info, err := os.Stat(file.Path); !force && err == nil && info.Mode().IsRegular() {
			clash = append(clash, file.Path)
		}
	}

	return clash
}

func write(blueprint Blueprint, root string) error {
	if err := os.MkdirAll(blueprint.Dir, 0o775); err != nil {
		return err
	}

	if blueprint.NewSkill {
		if err := os.MkdirAll(filepath.Dir(blueprint.SkillFile()), 0o775); err != nil {
			return err
		}

		if err := atomic.Write(blueprint.SkillFile(), SkillStub(blueprint)); err != nil {
			return err
		}
	}

	if err := atomic.Write(blueprint.RuleFile(), RuleStub(blueprint)); err != nil {
		return err
	}

	return custom.WriteSchema(root)
}

func report(blueprint Blueprint, registered bool, root string, console cli.Console) {
	sample := ".commandments/custom/samples/" + blueprint.Sin + "." + ProbeExtension(blueprint.Probe())

	console.Write("\033[32m✓ Scaffolded the `" + blueprint.ID + "` commandment.\033[0m\n")

	for _, file := range blueprint.Files() {
		console.Write("  " + strings.ReplaceAll(file.Path, root+"/", "") + "\033[2m  — " + file.Is + "\033[0m\n")
	}

	if registered {
		console.Write("  \033[2m" + config.EditorIn(root).Name() + "  — turned on under detectors: " + blueprint.Detector() + "\033[0m\n")
	} else {
		console.Write("  \033[2m" + config.EditorIn(root).Name() + "  — already turned on\033[0m\n")
	}

	console.Write("\n\033[1mNext — a scaffold is not a detector yet:\033[0m\n")

	for i, step := range steps(blueprint, sample) {
		console.Write("  \033[1;36m" + strconv.Itoa(i+1) + ".\033[0m " + step + "\n")
	}
}

func steps(blueprint Blueprint, sample string) []string {
	rule := strings.TrimPrefix(blueprint.RuleFile(), blueprint.Dir+"/")
	steps := []string{"\033[1mLoad the skill\033[0m \033[36mcommandments-writing-detectors\033[0m. It lists every check a\n" +
		"     step can make; a rule is only as good as the question each step asks."}

	if blueprint.NewSkill {
		steps = append(steps, "\033[1mWrite the teaching\033[0m — fill the TODOs in \033[36mskills/"+blueprint.Slug+"/SKILL.md\033[0m. It is\n"+
			"     what a finding sends the reader to, so write it before the rule: if you can't state what good\n"+
			"     looks like, the rule doesn't know what it's looking for either.")
	}

	return append(steps,
		"\033[1mName the sin\033[0m — fill its description and rule in \033[36m"+rule+"\033[0m. The description is\n"+
			"     the symptom, the rule is the positive directive.",
		"\033[1mWrite the query\033[0m — the `select` and its `where` in \033[36m"+rule+"\033[0m. Classify a node by\n"+
			"     what it IS (its kind, its roles, what its name resolves to) — never by a name list. \033[36m"+binary.Here()+" rule explain <file>\033[0m\n"+
			"     prints the tree a rule reads.",
		"\033[1mProve it fires\033[0m — write a sample at \033[36m"+sample+"\033[0m holding one example of\n"+
			"     EVERY form you mean to catch, each marked `@sin "+blueprint.Detector()+"` on the line above, plus a\n"+
			"     near-miss marked `@righteous "+blueprint.Detector()+"`, then run \033[36m"+binary.Here()+" rule prove\033[0m.\n"+
			"     The sample stays: it is the rule's spec.",
		"\033[1mCalibrate on real code\033[0m — run it with \033[36m--changes\033[0m or \033[36m--branch\033[0m over your source\n"+
			"     and READ the hits. Judge each against the skill, never against what the code happens to do:\n"+
			"     volume proves nothing, only a genuine false positive does. Tighten with a principled `reject`.",
		"\033[1mPublish the skill\033[0m — \033[36m"+binary.Here()+" sync\033[0m publishes\n"+
			"     \033[36m"+blueprint.SkillID()+"\033[0m so the agent can load what your finding points at.",
	)
}
