// Package exemptions is `exemptions`: the tags a package registers to quiet a general rule on its own boundary types, and the ones each detector honours.
package exemptions

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/info"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/engine"
)

// Command is `exemptions`.
type Command struct{}

func (Command) Names() []string {
	return []string{"exemptions"}
}

func (Command) Help() help.Help {
	return help.Of("List the exemption tags — what a package registers to quiet a general rule on its own boundary types.").
		Form("exemptions", "every registered tag, with the package that owns it").
		Form("exemptions <sin|detector>", "the exemptions ONE detector honours")
}

func (Command) Run(in *cli.Input, console cli.Console) (int, error) {
	query, named := in.FirstArgument()

	if !named {
		console.Write("\033[1mExemptions\033[0m — what a package registers to quiet a general rule (by slug or class).\n\n")

		for _, tag := range engine.ExemptionTags() {
			row(tag.Tag, tag.Package+": "+tag.Why, console)
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
		honoured := honouredTags(detector)

		if len(honoured) == 0 {
			console.Write("\033[1m" + name + "\033[0m honours no exemptions.\n")

			continue
		}

		console.Write("\033[1m" + name + "\033[0m honours:\n")

		for _, tag := range honoured {
			row(tag, why(tag), console)
		}
	}

	return 0, nil
}

// honouredTags are the exemption tags a rule asks, none for a detector that asks none.
func honouredTags(detector any) []string {
	if asks, honours := detector.(interface{ ExemptionTags() []string }); honours {
		return asks.ExemptionTags()
	}

	return nil
}

func why(tag string) string {
	var reasons []string
	for _, registered := range engine.ExemptionTags() {
		if registered.Tag == tag {
			reasons = append(reasons, registered.Package+": "+registered.Why)
		}
	}

	return strings.Join(reasons, "; ")
}

func row(tag, why string, console cli.Console) {
	console.Write("  \033[36m" + layout.PadBytes(tag, 16) + "\033[0m " + why + "\n")
}
