package config

import (
	"os"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

// Toggle is `disable` and `enable`: turn a sin or a whole skill off or back on in the project's config,
// edited through the tree so the file stays valid PHP and the project's own lines are untouched.
type Toggle struct{}

// Names are the verbs it answers to.
func (Toggle) Names() []string {
	return []string{"disable", "enable"}
}

// Help documents it.
func (Toggle) Help() help.Help {
	return help.Of("Toggle a rule in the project's .commandments/config.php — edited through the AST, so the file stays valid PHP and your own lines are untouched.").
		Form("disable <sin|skill>", "turn a rule off — a skill silences every detector it teaches the fix for").
		Form("enable <sin|skill>", "turn it back on").
		Note("The argument is a sin id OR a skill slug (the --sin= / --skill= keys), matched leniently. Run " +
			"`commandments judge --list` to see them all.")
}

// target is a sin or a skill the arguments name.
type target struct {
	class string
	label string
	named string
}

// Run toggles the rule the arguments name.
func (t Toggle) Run(in *cli.Input, console cli.Console) (int, error) {
	action := in.Command()
	query, named := in.FirstArgument()

	if !named {
		return help.Usage(console.Err, t, "Name the sin or skill to "+action+"."), nil
	}

	matches := matching(query)

	switch {
	case len(matches) == 0:
		console.Warn("No sin or skill matches \"" + query + "\". Run `commandments judge --list` to see them.")

		return 2, nil
	case len(matches) > 1:
		var names []string

		for _, match := range matches {
			names = append(names, match.named)
		}

		console.Warn("\"" + query + "\" matches more than one: " + strings.Join(names, ", ") + ". Name the one you mean.")

		return 2, nil
	}

	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	file := EditorIn(root)
	toggle := file.Disable

	if action == "enable" {
		toggle = file.Enable
	}

	changed, err := toggle(matches[0].class)
	if err != nil {
		return 0, err
	}

	return report(action, matches[0], changed, console), nil
}

func report(action string, chosen target, changed bool, console cli.Console) int {
	verb, noun := "disabled", "already disabled"

	if action == "enable" {
		verb, noun = "enabled", "was not disabled"
	}

	if changed {
		return console.Say("\033[32m✓ " + verb + " " + chosen.label + ".\033[0m")
	}

	return console.Say("\033[2m" + chosen.label + " " + noun + " — nothing to do.\033[0m")
}

// matching are the sins and skills the query names exactly, else leniently: sins first, then skills.
func matching(query string) []target {
	var exact, lenient []target

	for _, sin := range sins.All() {
		definition := sin.Definition()
		each := target{ClassOf(Sin, sin), "`" + definition.Name + "`", definition.Name}

		if definition.Name == query {
			exact = append(exact, each)
		}

		if definition.Matches(query) {
			lenient = append(lenient, each)
		}
	}

	for _, taught := range skill.All() {
		definition := taught.Definition()
		each := target{ClassOf(Skill, taught), "skill `" + definition.Slug + "`", definition.Slug}

		if definition.Slug == query {
			exact = append(exact, each)
		}

		if definition.Matches(query) {
			lenient = append(lenient, each)
		}
	}

	if len(exact) > 0 {
		return exact
	}

	return lenient
}
