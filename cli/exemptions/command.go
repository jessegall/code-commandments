// Package exemptions is `exemptions`: the tags a package registers to quiet a general rule on its own
// boundary types, and the ones each detector honours.
package exemptions

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/info"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/engine/php/packages"
)

// Command is `exemptions`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"exemptions"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("List the exemption tags — what a package registers to quiet a general rule on its own boundary types.").
		Form("exemptions", "every registered tag, with the package that owns it").
		Form("exemptions <sin|detector>", "the exemptions ONE detector honours")
}

// Run lists every tag, or the ones the named detectors honour.
func (Command) Run(in *cli.Input, console cli.Console) (int, error) {
	query, named := in.FirstArgument()

	if !named {
		console.Write("\033[1mExemptions\033[0m — what a package registers to quiet a general rule (by slug or class).\n\n")

		for _, tag := range packages.Tags {
			row(tag, console)
		}

		console.Write("\n\033[2mRun `commandments exemptions <sin|detector>` to see one detector's exemptions.\033[0m\n")

		return 0, nil
	}

	found := info.Detectors(query)

	if len(found) == 0 {
		console.Warn("No sin or detector matches \"" + query + "\". Run `commandments judge --list` to see them.")

		return 2, nil
	}

	for _, detector := range found {
		name := catalog.Name(detector)
		exemptable, honours := detector.(packages.Exemptable)

		if !honours || len(exemptable.Exemptions()) == 0 {
			console.Write("\033[1m" + name + "\033[0m honours no exemptions.\n")

			continue
		}

		console.Write("\033[1m" + name + "\033[0m honours:\n")

		for _, exemption := range exemptable.Exemptions() {
			row(exemption.Tag, console)
		}
	}

	return 0, nil
}

func row(tag packages.Tag, console cli.Console) {
	console.Write("  \033[36m" + layout.PadBytes(tag.Slug, 16) + "\033[0m " + tag.Description + "\n")
}
