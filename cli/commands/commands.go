// Package commands is the tool's whole verb surface: every command, registered in the order the overview lists them.
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
	"github.com/jessegall/code-commandments/cli/rules"
	"github.com/jessegall/code-commandments/cli/scaffold"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/service"
	"github.com/jessegall/code-commandments/cli/session"
	"github.com/jessegall/code-commandments/cli/sync"
	"github.com/jessegall/code-commandments/cli/task"
	"github.com/jessegall/code-commandments/cli/triggers"
	_ "github.com/jessegall/code-commandments/registry"
)

func Kernel(version string) *cli.Kernel {
	commands := []cli.Command{
		judge.Command{Fixable: repent.Fixable("latest"), Scaffoldable: scaffold.Scaffoldable()},
		makecommand.Command{},
		hints.Command{},
		repent.Command{},
		scaffold.Command{},
		report.Command{Version: version},
		report.FeatureRequest{Version: version},
		freeze.Command{},
		sync.Command{},
		sync.Install{},
		hooks.Single{Verbs: []string{"judge-reminder"}, Hook: hooks.JudgeReminder{}, About: hooks.JudgeReminderAbout},
		session.Command{},
		task.Command{},
		hooks.Dispatch{},
		hooks.PluginHook{},
	}
	commands = append(commands, bridgeServices()...)
	commands = append(commands,
		hooks.Lanes{},
		hooks.Why{},
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

	return cli.NewKernel(version, commands...)
}

// bridgeServices are a serve verb for every bridge that runs as a process of its own, and a has verb for every
// bridge, so a bridge added to scan.Bridges gets its verbs with no edit here.
func bridgeServices() []cli.Command {
	var serves, has []cli.Command
	for _, held := range scan.Bridges {
		if held.RunsAsAProcess() {
			serves = append(serves, service.Serve{Bridge: held})
		}
		has = append(has, service.Has{Bridge: held})
	}

	return append(serves, has...)
}
