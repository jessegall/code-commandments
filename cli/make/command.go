package make

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/skill"
)

// Command is `make`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"make"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Scaffold a commandment of your own — a rule naming its sin, and the skill that teaches the fix, in `.commandments/custom/`, turned on in your config, with the rest of the process printed for you.").
		Form("make <Name>", "scaffold a backend (PHP) commandment and turn it on").
		Form("make <Name> --engine=frontend", "scaffold a frontend (Vue) one instead").
		Form("make <Name> --engine=typescript", "scaffold a TypeScript one instead").
		Form("make <Name> --engine=python", "scaffold a Python one instead").
		Form("make <Name> --engine=csharp", "scaffold a C# one instead").
		Form("make <Name> --skill=NAME", "point the sin at an EXISTING skill (shipped or your own) instead of writing a new one").
		Form("make <Name> --from=<template>", "start from a ready rule to adapt: "+templateNames()).
		Option("--engine=backend|frontend|typescript|python|csharp", "which engine the rule judges (default: backend)").
		Option("--skill=NAME", "the skill that teaches the fix — a lenient name/slug match against the existing skills, or a new slug to create one").
		Option("--from=<template>", "the ready rule to start from, its query written for the engine").
		Option("--force", "overwrite files that already exist").
		Note("A rule is data the binary runs: `<Name>Detector.json` names its engine, its sin and a query — a " +
			"selector, then `where` and `reject` steps of one check each. A new skill is `skills/<slug>/SKILL.md`, " +
			"published as you write it. The folder is kept OUT of the .commandments/ gitignore — your rules are " +
			"source, so commit them.").
		Note("A scaffolded rule does not work yet, and is not meant to: its query finds nothing until you write " +
			"it. Load the `commandments-writing-detectors` skill first — it lists the checks a step can make and " +
			"teaches the probe-then-calibrate discipline that proves a rule fires on what you meant.")
}

// Run scaffolds the commandment the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	name, named := in.FirstArgument()
	if !named || Studly(name) == "" {
		return help.Usage(console.Err, c, "name the commandment, e.g. `commandments make NullableElementReturn`."), nil
	}

	engine := Backend

	if given, set := in.Option("engine"); set {
		parsed, known := ParseEngine(given)
		if !known {
			return help.Usage(console.Err, c, "unknown --engine="+given+" — it is one of `backend`, `frontend`, `typescript`, `python`, `csharp`."), nil
		}

		engine = parsed
	}

	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	query, _ := in.Option("skill")
	blueprint := plan(name, engine, query, root)

	if from, set := in.Option("from"); set {
		template, known := TemplateNamed(from)
		if !known {
			return help.Usage(console.Err, c, "no template `"+from+"` — the ready rules are "+templateNames()+"."), nil
		}

		if _, written := template.Query(engine); !written {
			return help.Usage(console.Err, c, "the `"+from+"` template is written for "+engineList(template.Engines())+", not --engine="+string(engine)+"."), nil
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

	if _, err := config.Migrate(root); err != nil {
		return 0, err
	}

	registered, err := config.EditorIn(root).RegisterDetector(blueprint.Detector())
	if err != nil {
		return 0, err
	}

	report(blueprint, registered, root, console)

	return 0, nil
}

// plan names the commandment: its sin points at the first existing skill the query matches, shipped or the
// project's own, else at a new skill of the project's own for the query's slug or its own name.
func plan(name string, engine Engine, query, root string) Blueprint {
	dir := workspace.CustomDir(root)

	if query != "" {
		for _, teaching := range append(skill.Ordered(), ownSkills(root)...) {
			if definition := teaching.Definition(); definition.Matches(query) {
				return Of(name, engine, definition.Slug, false, dir)
			}
		}
	}

	blueprint := Of(name, engine, "", true, dir)
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

		if err := os.WriteFile(blueprint.SkillFile(), []byte(SkillStub(blueprint)), 0o644); err != nil {
			return err
		}
	}

	if err := os.WriteFile(blueprint.RuleFile(), []byte(RuleStub(blueprint)), 0o644); err != nil {
		return err
	}

	return custom.WriteSchema(root)
}

func report(blueprint Blueprint, registered bool, root string, console cli.Console) {
	probe := blueprint.Engine.ProbeRoot() + "/Probe" + blueprint.Sin + "." + blueprint.Engine.ProbeExtension()

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

	for i, step := range steps(blueprint, probe) {
		console.Write("  \033[1;36m" + strconv.Itoa(i+1) + ".\033[0m " + step + "\n")
	}
}

func steps(blueprint Blueprint, probe string) []string {
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
		"\033[1mWrite the query\033[0m — the `select`, then `where` and `reject` in \033[36m"+rule+"\033[0m. One check\n"+
			"     per step, classified by what the node IS (its neutral kind, what its name resolves to) — never by a\n"+
			"     name list.",
		"\033[1mProve it fires\033[0m — write a throwaway probe at \033[36m"+probe+"\033[0m holding one example of\n"+
			"     EVERY form you mean to catch plus a near-miss you must NOT, then run\n"+
			"     \033[36mcommandments judge "+blueprint.Engine.ProbeRoot()+" --sin="+blueprint.ID+" --no-checklist\033[0m\n"+
			"     and confirm exactly the intended lines are flagged. Delete the probe after.",
		"\033[1mCalibrate on real code\033[0m — run it with \033[36m--changes\033[0m or \033[36m--branch\033[0m over your source\n"+
			"     and READ the hits. Judge each against the skill, never against what the code happens to do:\n"+
			"     volume proves nothing, only a genuine false positive does. Tighten with a principled `reject`.",
		"\033[1mPublish the skill\033[0m — \033[36mcommandments sync\033[0m publishes\n"+
			"     \033[36m"+blueprint.SkillID()+"\033[0m so the agent can load what your finding points at.",
	)
}
