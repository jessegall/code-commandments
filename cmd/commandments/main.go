// Command commandments is the whole tool: it judges a codebase against the architectural disciplines and
// runs every verb around that.
package main

import (
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/freeze"
	"github.com/jessegall/code-commandments/cli/info"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/layers"
	makecommand "github.com/jessegall/code-commandments/cli/make"
	"github.com/jessegall/code-commandments/cli/report"
	"github.com/jessegall/code-commandments/cli/scaffold"
	"github.com/jessegall/code-commandments/cli/session"
	"github.com/jessegall/code-commandments/cli/task"
	"github.com/jessegall/code-commandments/cli/triggers"
	_ "github.com/jessegall/code-commandments/registry"
)

// version is stamped by the release build; a local build is dev.
var version = "dev"

func main() {
	os.Exit(Kernel().Run(os.Args[1:], cli.Console{Out: os.Stdout, Err: os.Stderr}))
}

// Kernel is the kernel with every command registered, in the order the overview lists them.
func Kernel() *cli.Kernel {
	return cli.NewKernel(version,
		judge.Command{Scaffoldable: scaffold.Scaffoldable()},
		makecommand.Command{},
		scaffold.Command{},
		report.Command{},
		report.FeatureRequest{},
		freeze.Command{},
		session.Command{},
		task.Command{},
		config.Toggle{},
		config.Command{Version: version},
		layers.Command{},
		info.Command{Scaffoldable: scaffold.Scaffoldable()},
		triggers.Command{},
	)
}
