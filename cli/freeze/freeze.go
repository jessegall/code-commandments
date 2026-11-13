// Package freeze is `freeze` and `unfreeze`: stamp a file deliberately immutable, or lift the stamp.
package freeze

import (
	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/contract"
	"os"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
)

// Command is `freeze` and `unfreeze`.
type Command struct{}

func (Command) Names() []string {
	return []string{"freeze", "unfreeze"}
}

func (Command) Help() help.Help {
	return help.Of("Mark a file intentionally immutable, or lift the mark. A frozen file is still scanned (so cross-file rules stay correct) but never flagged and never rewritten.").
		Form("freeze <path>", "stamp the file frozen").
		Form("unfreeze <path>", "lift the stamp — the file is a target again").
		Note("Idempotent: freezing a frozen file (or unfreezing an unfrozen one) reports and does nothing.")
}

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

	if !source.Judges(path) {
		console.Warn("Not a file of a language the tool judges, so there is nothing to freeze: " + path)

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

func stamped(contents string, language source.Language) string {
	stamp := language.Comment(engine.FrozenMarker + " — deliberately immutable; excluded from code-commandments " +
		"judging & repent (run `" + binary.Here() + " unfreeze` to lift).")

	if !contract.OpensFirst(language.Written(), contents) {
		return stamp + "\n" + contents
	}

	firstLineEnd := strings.Index(contents, "\n")
	if firstLineEnd < 0 {
		return contents + "\n" + stamp + "\n"
	}

	return contents[:firstLineEnd+1] + stamp + "\n" + contents[firstLineEnd+1:]
}
