// Command commandments is the whole tool: it judges a codebase against the architectural disciplines and
// runs every verb around that.
package main

import (
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/commands"
)

// version is stamped by the release build; a local build is dev.
var version = "dev"

func main() {
	os.Exit(commands.Kernel(version).Run(os.Args[1:], cli.Console{Out: os.Stdout, Err: os.Stderr}))
}
