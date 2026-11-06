package hooks

import (
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Why is `why`: what the last judging of a file was, and what it could not ask.
type Why struct{}

func (Why) Names() []string {
	return []string{"why"}
}

func (Why) Help() help.Help {
	return help.Of("Say what the last judging of a file was: the checkout it was judged in, whether a tree was held, which rules were asked, and what was found.").
		Form("why <file>", "the judgings of that file, newest last").
		Form("why", "the last judgings of every file").
		Note("It reads the record every judging writes, which lives in the plugin's own folder beside the session's " +
			"state. A file with no line in it was judged by nobody, which is not the same as a file with no sins.").
		In(help.Hooks)
}

func (Why) Run(in *cli.Input, console cli.Console) (int, error) {
	asked, _ := in.FirstArgument()

	folder := os.Getenv(pluginData)
	if folder == "" {
		folder = workspace.At(workspace.ProjectRoot(cwdOf()), "").SessionDir()
	}

	read, err := os.ReadFile(filepath.Join(folder, "judgings.log"))
	if err != nil {
		console.Warn("✋ nothing has been judged in this session, or the record is not where this run looks: " +
			filepath.Join(folder, "judgings.log"))

		return 2, nil
	}

	var said []string
	for _, line := range strings.Split(strings.TrimSpace(string(read)), "\n") {
		if asked == "" || strings.Contains(line, asked) {
			said = append(said, line)
		}
	}

	if len(said) == 0 {
		console.Say("Nothing has judged " + asked + " in this session. A file nobody judged is not a file with no sins.")

		return 0, nil
	}

	console.Say(strings.Join(said, "\n"))

	return 0, nil
}

func cwdOf() string {
	here, _ := os.Getwd()

	return here
}
