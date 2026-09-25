package sync

import (
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/hooks"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Install is `install`: wire a project up once, then sync it.
type Install struct{}

// Names are the verbs it answers to.
func (Install) Names() []string {
	return []string{"install"}
}

// Help documents it.
func (Install) Help() help.Help {
	return help.Of("Wire a consumer project up once — the composer sync hook, every agent it supports (skills, AGENTS.md, and under Claude Code the hook suite) and .gitignore — then sync.").
		Form("install", "wire it (idempotent; every hook we write is stamped, so your own hooks are never touched)")
}

// Run wires the composer scripts and the Claude hooks of the project the working folder is in, then syncs.
func (c Install) Run(in *cli.Input, console cli.Console) (int, error) {
	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	root := ConsumerRoot(cwd)
	if root == "" {
		return help.Usage(console.Err, c, "no composer.json at or above "+cwd+" — there is no project here to wire."), nil
	}

	wired, has, err := EnsureComposerHook(root)
	if err != nil {
		return 0, err
	}

	if !has && fileExists(root+"/composer.json") {
		return help.Usage(console.Err, c, root+"/composer.json is not readable JSON — fix it first; rewriting it from here would throw away everything it declares."), nil
	}

	hooked, err := hooks.Wire(root)
	if err != nil {
		console.Warn(err.Error())
	}

	switch {
	case wired:
		console.Say("✓ Wired `commandments sync` into composer post-update-cmd / post-install-cmd.")
	case has:
		console.Say("✓ composer hooks already wired.")
	}

	if hooked {
		console.Say("✓ Wired the Claude Code hooks: the cardinal-rule reminder (PostToolUse) + the judge nudge (Stop).")
	} else {
		console.Say("✓ Claude Code hooks already wired.")
	}

	release := hold(workspace.At(root, "").Shared(".sync.lock"))
	defer release()

	return 0, Sync(root, console)
}

func fileExists(path string) bool {
	info, err := os.Stat(path)

	return err == nil && info.Mode().IsRegular()
}
