// Package commands is the tool's whole verb surface: every command, registered in the order the overview
// lists them. Registering one here is wiring it, and documenting it.
package commands

import (
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/exemptions"
	"github.com/jessegall/code-commandments/cli/freeze"
	"github.com/jessegall/code-commandments/cli/hints"
	"github.com/jessegall/code-commandments/cli/hooks"
	"github.com/jessegall/code-commandments/cli/info"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/layers"
	makecommand "github.com/jessegall/code-commandments/cli/make"
	"github.com/jessegall/code-commandments/cli/repent"
	"github.com/jessegall/code-commandments/cli/report"
	"github.com/jessegall/code-commandments/cli/roslyn"
	"github.com/jessegall/code-commandments/cli/rules"
	"github.com/jessegall/code-commandments/cli/scaffold"
	"github.com/jessegall/code-commandments/cli/session"
	"github.com/jessegall/code-commandments/cli/sync"
	"github.com/jessegall/code-commandments/cli/task"
	"github.com/jessegall/code-commandments/cli/triggers"
	_ "github.com/jessegall/code-commandments/registry"
)

// Kernel is the kernel with every command registered, in the order the overview lists them.
func Kernel(version string) *cli.Kernel {
	return cli.NewKernel(version,
		judge.Command{Fixable: repent.Fixable("latest"), Scaffoldable: scaffold.Scaffoldable()},
		makecommand.Command{},
		hints.Command{},
		repent.Command{},
		scaffold.Command{},
		report.Command{},
		report.FeatureRequest{},
		freeze.Command{},
		sync.Command{},
		sync.Install{},
		hooks.Single{Verbs: []string{"judge-reminder"}, Hook: hooks.JudgeReminder{}, About: hooks.JudgeReminderAbout},
		session.Command{},
		task.Command{},
		hooks.Dispatch{},
		hooks.JournalHook{},
		hooks.JournalServe{},
		roslyn.Serve{},
		hooks.JournalConfig{},
		hooks.JournalScan{},
		hooks.JournalSkills{},
		hooks.Runner{},
		config.Toggle{},
		config.Command{Version: version},
		layers.Command{},
		rules.Command{},
		exemptions.Command{},
		info.Command{Scaffoldable: scaffold.Scaffoldable()},
		triggers.Command{},
	)
}
