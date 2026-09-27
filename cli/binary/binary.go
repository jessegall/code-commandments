// Package binary says where a project's commandments executable is, so every command written into the
// project (a wired hook, the composer sync call) names a file that is really there.
package binary

import (
	"os"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// candidates are where the executable lives: a consumer's composer shim, else a checkout's own bin/.
var candidates = []string{Shim, "bin/commandments"}

// Name is the executable as a project with no PHP runs it: from the PATH it was installed on.
const Name = "commandments"

// ThroughPHP says whether the project at root runs the tool through PHP: it has a composer.json, or one of
// the PHP entry points is already there.
func ThroughPHP(root string) bool {
	if info, err := os.Stat(root + "/composer.json"); err == nil && info.Mode().IsRegular() {
		return true
	}

	for _, candidate := range candidates {
		if info, err := os.Stat(root + "/" + candidate); err == nil && info.Mode().IsRegular() {
			return true
		}
	}

	return false
}

// In is the executable of the project at root, relative to it: the first candidate present, else the shim
// a project will have once it installs, so wiring it before then still writes the command that works.
func In(root string) string {
	for _, candidate := range candidates {
		if info, err := os.Stat(root + "/" + candidate); err == nil && info.Mode().IsRegular() {
			return candidate
		}
	}

	return candidates[0]
}

// Shim is how a project that installs the tool with composer runs it, as every command the tool prints
// names it there.
const Shim = "vendor/bin/commandments"

// Invocation is the command a person or an agent types in the project at root to run the tool: the
// composer shim where the project installs it through PHP, else the binary from the PATH.
func Invocation(root string) string {
	if ThroughPHP(root) {
		return Shim
	}

	return Name
}

// Here is the invocation for the project the working folder is in.
func Here() string {
	cwd, _ := os.Getwd()

	return Invocation(workspace.ProjectRoot(cwd))
}
