// Package curriculum is `skills`: the installed curriculum the binary reads loose, checked so a release that changes only lessons runs on a binary that reads them.
package curriculum

import (
	"fmt"
	"io/fs"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	_ "github.com/jessegall/code-commandments/rules"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skills"
)

// Command is `skills`.
type Command struct{}

func (Command) Names() []string {
	return []string{"skills"}
}

func (Command) Help() help.Help {
	return help.Of("Check the installed skills the binary reads loose.").
		Form("skills check", "load every skill's SKILL.md from the installed skills folder and print each one that is missing or names another skill; fails when one does")
}

func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	if verb, _ := in.FirstArgument(); verb != "check" {
		return help.Usage(console.Err, c, "say what to do: `skills check`."), nil
	}
	problems := Problems(skills.Files)
	for _, problem := range problems {
		console.Write(problem + "\n")
	}
	if len(problems) > 0 {
		return 1, nil
	}
	console.Write(fmt.Sprintf("%d installed skills load\n", len(skill.All())))

	return 0, nil
}

func Problems(curriculum fs.FS) []string {
	var problems []string
	for _, taught := range skill.All() {
		definition := taught.Definition()
		at := "commandments/" + definition.Slug + "/SKILL.md"
		raw, err := fs.ReadFile(curriculum, at)
		if err != nil {
			problems = append(problems, at+": missing")

			continue
		}
		if !strings.Contains(string(raw), "\nname: "+definition.ID()+"\n") {
			problems = append(problems, at+": does not name "+definition.ID())
		}
	}

	return problems
}
