// Package freeze is `freeze` and `unfreeze`: stamp a file deliberately immutable, or lift the stamp. A
// frozen file is still scanned, so cross-file rules stay correct, but never flagged and never rewritten.
package freeze

import (
	"os"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
)

// Command is `freeze` and `unfreeze`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"freeze", "unfreeze"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Mark a file intentionally immutable, or lift the mark. A frozen file is still scanned (so cross-file rules stay correct) but never flagged and never rewritten.").
		Form("freeze <path>", "stamp the file frozen").
		Form("unfreeze <path>", "lift the stamp — the file is a target again").
		Note("Idempotent: freezing a frozen file (or unfreezing an unfrozen one) reports and does nothing.")
}

// Run stamps or unstamps the file the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	path, named := in.FirstArgument()
	if !named {
		return help.Usage(console.Err, c, "Name the file to "+in.Command()+"."), nil
	}

	raw, err := os.ReadFile(path)
	if info, statErr := os.Stat(path); err != nil || statErr != nil || !info.Mode().IsRegular() {
		console.Warn("No such file: " + path)

		return 2, nil
	}

	if in.Command() == "unfreeze" {
		return unfreeze(path, string(raw), console)
	}

	return freeze(path, string(raw), console)
}

func freeze(path, contents string, console cli.Console) (int, error) {
	if strings.Contains(contents, engine.FrozenMarker) {
		return console.Say("\033[2mAlready frozen: " + path + "\033[0m"), nil
	}

	if err := os.WriteFile(path, []byte(stamped(contents, source.OfFile(path))), 0o644); err != nil {
		return 0, err
	}

	return console.Say("\033[32m✓ Frozen " + path + "\033[0m — scanned for resolution, but never flagged or repented."), nil
}

func unfreeze(path, contents string, console cli.Console) (int, error) {
	if !strings.Contains(contents, engine.FrozenMarker) {
		return console.Say("\033[2mNot frozen: " + path + "\033[0m"), nil
	}

	var kept []string

	for _, line := range strings.Split(contents, "\n") {
		if !strings.Contains(line, engine.FrozenMarker) {
			kept = append(kept, line)
		}
	}

	if err := os.WriteFile(path, []byte(strings.Join(kept, "\n")), 0o644); err != nil {
		return 0, err
	}

	return console.Say("\033[32m✓ Unfroze " + path + "\033[0m — it is a target again."), nil
}

// stamped is the source with the freeze stamp as its first line, or its second after PHP's opening line or
// a shebang.
func stamped(contents string, language source.Language) string {
	stamp := language.Comment(engine.FrozenMarker + " — deliberately immutable; excluded from code-commandments " +
		"judging & repent (run `commandments unfreeze` to lift).")

	if language != source.PHP && !strings.HasPrefix(contents, "#!") {
		return stamp + "\n" + contents
	}

	firstLineEnd := strings.Index(contents, "\n")
	if firstLineEnd < 0 {
		return contents + "\n" + stamp + "\n"
	}

	return contents[:firstLineEnd+1] + stamp + "\n" + contents[firstLineEnd+1:]
}
