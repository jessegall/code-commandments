// Command commandments is the whole tool: it judges a codebase against the architectural disciplines and
// runs every verb around that.
package main

import (
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/session"
)

// version is stamped by the release build; a local build is dev.
var version = "dev"

func main() {
	os.Exit(Kernel().Run(os.Args[1:], cli.Console{Out: os.Stdout, Err: os.Stderr}))
}

// Kernel is the kernel with every command registered, in the order the overview lists them.
func Kernel() *cli.Kernel {
	return cli.NewKernel(version,
		session.Command{},
	)
}
