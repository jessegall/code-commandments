package report

import (
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

// FeatureRequest is `feature-request`: propose a new or changed rule.
type FeatureRequest struct {
	Version string
}

// Names are the verbs it answers to.
func (FeatureRequest) Names() []string {
	return []string{"feature-request"}
}

// Help documents it.
func (FeatureRequest) Help() help.Help {
	return help.Of("File a [feature-request] GitHub issue (via `gh`) proposing a new or changed rule.").
		Form(`feature-request --title="…" --reason="…"`, "propose it").
		Option(`--title="…"`, "a short title for the proposal (required)").
		Option(`--reason="…"`, "what to add or change, and why (required)")
}

// Run files the proposal.
func (f FeatureRequest) Run(in *cli.Input, console cli.Console) (int, error) {
	title, titled := in.Option("title")
	reason, reasoned := in.Option("reason")

	if !titled || !reasoned {
		return help.Usage(console.Err, f, "--title and --reason are both required."), nil
	}

	body := "**Proposal:**\n" + reason + "\n" + filedBy("feature-request", f.Version)

	return File("[feature-request] "+title, body, console), nil
}
