package make

import (
	"os"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
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
	return help.Of("Scaffold a commandment of your own — a skill, a sin and a detector in `.commandments/custom/`, registered in your config, with the rest of the process printed for you.").
		Form("make <Name>", "scaffold a backend (PHP) commandment and register it").
		Form("make <Name> --engine=frontend", "scaffold a frontend (Vue) one instead").
		Form("make <Name> --engine=python", "scaffold a Python one instead").
		Form("make <Name> --engine=csharp", "scaffold a C# one instead").
		Form("make <Name> --skill=NAME", "point the sin at an EXISTING skill (shipped or your own) instead of writing a new one").
		Option("--engine=backend|frontend|python|csharp", "which parse engine the detector reads (default: backend)").
		Option("--skill=NAME", "the skill that teaches the fix — a lenient name/slug match against the existing skills, or a new slug to create one").
		Option("--force", "overwrite files that already exist").
		Note("The generated classes live in `.commandments/custom/`, beside your config. That folder is not " +
			"PSR-4 mapped and does not need to be: its files are required directly, so dropping a class in is " +
			"what makes it loadable. It is kept OUT of the .commandments/ gitignore — your rules are source " +
			"code, so commit them.").
		Note("A scaffolded detector does not work yet, and is not meant to: its `find()` returns nothing until " +
			"you write the rule. Load the `commandments-writing-detectors` skill first — it lists the engine " +
			"predicates that already exist (hand-rolling one that does is the most common mistake), and it " +
			"teaches the probe-then-calibrate discipline that proves a detector actually fires on what you meant.")
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
			return help.Usage(console.Err, c, "unknown --engine="+given+" — it is one of `backend`, `frontend`, `python`, `csharp`."), nil
		}

		engine = parsed
	}

	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	query, _ := in.Option("skill")
	blueprint := plan(name, engine, query, root)

	if clash := existing(blueprint, in.HasFlag("force")); len(clash) > 0 {
		console.Warn("Already there — pass --force to overwrite:\n  " + strings.Join(clash, "\n  "))

		return 2, nil
	}

	if err := write(blueprint); err != nil {
		return 0, err
	}

	registered, err := config.EditorIn(root).RegisterDetector(blueprint.DetectorClass())
	if err != nil {
		return 0, err
	}

	report(blueprint, registered, root, console)

	return 0, nil
}

// plan names the commandment's classes: its sin points at the first existing skill the query matches,
// else at a skill of its own for the query's slug or its own name.
func plan(name string, engine Engine, query, root string) Blueprint {
	dir := workspace.CustomDir(root)

	if query != "" {
		for _, teaching := range skill.Ordered() {
			if definition := teaching.Definition(); definition.Matches(query) {
				return Of(name, engine, definition.Slug, config.ClassOf(config.Skill, teaching), dir)
			}
		}
	}

	slug := query
	if slug == "" {
		slug = Kebab(name)
	}

	if !strings.Contains(slug, "/") {
		slug = string(engine) + "/" + Kebab(slug)
	}

	return Of(name, engine, slug, "", dir)
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

func write(blueprint Blueprint) error {
	if err := os.MkdirAll(blueprint.Dir, 0o775); err != nil {
		return err
	}

	type file struct{ path, code string }
	var files []file

	if blueprint.Skill != "" {
		files = append(files, file{blueprint.Dir + "/" + blueprint.Skill + ".php", SkillStub(blueprint)})
	}

	files = append(files,
		file{blueprint.Dir + "/" + blueprint.Sin + ".php", SinStub(blueprint)},
		file{blueprint.Dir + "/" + blueprint.Detector() + ".php", DetectorStub(blueprint)},
	)

	// In the order PHP writes them: on a case-insensitive disk two of the names can be one file.
	for _, written := range files {
		if err := os.WriteFile(written.path, []byte(written.code), 0o644); err != nil {
			return err
		}
	}

	return nil
}

func report(blueprint Blueprint, registered bool, root string, console cli.Console) {
	probe := blueprint.Engine.ProbeRoot() + "/Probe" + blueprint.Sin + "." + blueprint.Engine.ProbeExtension()

	console.Write("\033[32m✓ Scaffolded the `" + blueprint.ID + "` commandment.\033[0m\n")

	for _, file := range blueprint.Files() {
		console.Write("  " + strings.ReplaceAll(file.Path, root+"/", "") + "\033[2m  — " + file.Class + "\033[0m\n")
	}

	if registered {
		console.Write("  \033[2m" + config.EditorIn(root).Name() + "  — registered ->detector(" + blueprint.Detector() + "::class)\033[0m\n")
	} else {
		console.Write("  \033[2m" + config.EditorIn(root).Name() + "  — already registered\033[0m\n")
	}

	console.Write("\n\033[1mNext — a scaffold is not a detector yet:\033[0m\n")

	for i, step := range steps(blueprint, probe) {
		console.Write("  \033[1;36m" + strconv.Itoa(i+1) + ".\033[0m " + step + "\n")
	}

	console.Write("\n\033[2mThe skill's SKILL.md is generated from the class on every sync — edit the class, never the markdown.\033[0m\n")
}

func steps(blueprint Blueprint, probe string) []string {
	steps := []string{"\033[1mLoad the skill\033[0m \033[36mcommandments-writing-detectors\033[0m. It lists the engine\n" +
		"     predicates that ALREADY exist; hand-rolling one that does is the mistake this command exists to prevent."}

	if blueprint.Skill != "" {
		steps = append(steps, "\033[1mWrite the teaching\033[0m — fill the TODOs in \033[36m"+blueprint.Skill+"\033[0m. It is what a finding\n"+
			"     sends the reader to, so write it before the rule: if you can't state what good looks like,\n"+
			"     the detector doesn't know what it's looking for either.")
	}

	return append(steps,
		"\033[1mName the sin\033[0m — fill the description/rule in \033[36m"+blueprint.Sin+"\033[0m. The description is the\n"+
			"     symptom, the rule is the positive directive. Both are projected into the docs.",
		"\033[1mWrite the rule\033[0m — the `where()` chain in \033[36m"+blueprint.Detector()+"\033[0m. One check per line,\n"+
			"     classified by what the AST or the resolved type IS — never by a name or a suffix.",
		"\033[1mProve it fires\033[0m — write a throwaway probe at \033[36m"+probe+"\033[0m holding one example of\n"+
			"     EVERY form you mean to catch plus a near-miss you must NOT, then run\n"+
			"     \033[36mvendor/bin/commandments judge "+blueprint.Engine.ProbeRoot()+" --sin="+blueprint.ID+" --no-checklist\033[0m\n"+
			"     and confirm exactly the intended lines are flagged. Delete the probe after.",
		"\033[1mCalibrate on real code\033[0m — run the same judge over your actual source and READ the hits.\n"+
			"     Judge each against the skill, never against what the code happens to do: volume proves\n"+
			"     nothing, only a genuine false positive does. Tighten with a principled `reject`, never a name list.",
		"\033[1mPublish the skill\033[0m — \033[36mvendor/bin/commandments sync\033[0m renders\n"+
			"     \033[36m"+blueprint.SkillID()+"\033[0m so the agent can load what your finding points at.",
	)
}
