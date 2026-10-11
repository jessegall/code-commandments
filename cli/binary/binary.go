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

// present says whether the file at path, relative to root, is really there: the one question every answer in
// this package turns on, since a command naming a file that is not there is the bug it exists to prevent.
func present(root, path string) bool {
	info, err := os.Stat(root + "/" + path)

	return err == nil && info.Mode().IsRegular()
}

// ThroughPHP says whether the project at root runs the tool through PHP: it has a composer.json, or one of
// the PHP entry points is already there.
func ThroughPHP(root string) bool {
	if present(root, "composer.json") {
		return true
	}

	for _, candidate := range candidates {
		if present(root, candidate) {
			return true
		}
	}

	return false
}

// In is the executable of the project at root, relative to it: the first candidate present, else the shim
// a project will have once it installs, so wiring it before then still writes the command that works.
func In(root string) string {
	for _, candidate := range candidates {
		if present(root, candidate) {
			return candidate
		}
	}

	return candidates[0]
}

// Launcher is the script of the project at root that runs a hook without starting PHP, relative to it: in the
// package composer installs where the project runs the shim, else in the checkout's own bin/.
func Launcher(root string) string {
	if In(root) == Shim {
		return "vendor/jessegall/code-commandments/bin/hook"
	}

	return "bin/hook"
}

// Release is the binary the journal's plugin fetches, inside the plugin's own folder, as its manifest names it.
const Release = "bin/commandments-release"

// Shim is how a project that installs the tool with composer runs it, as every command the tool prints
// names it there.
const Shim = "vendor/bin/commandments"

// Invocation is the command a person or an agent types in the project at root to run the tool: the PHP entry
// point the project really has, the plugin's own binary where the agent journal installed it (it puts nothing on
// the PATH), the shim a composer project will have once it installs, else the binary from the PATH. A file that is
// there always beats one a project is only expected to get, which is why the plugin stands between them.
func Invocation(root string) string {
	entry, php := In(root), ThroughPHP(root)

	if php && present(root, entry) {
		return entry
	}

	if plugin := ThroughPlugin(root); plugin != "" {
		return plugin
	}

	if php {
		return entry
	}

	return Name
}

// ThroughPlugin is the binary the agent journal's plugin installed in the project at root, relative to it, or
// none where the journal does not run the tool. The plugin fetches its own release binary into its own folder and
// puts nothing on the PATH, so a project it drives has no `commandments` to type: every command the tool writes
// into such a project has to name the file the plugin actually holds.
func ThroughPlugin(root string) string {
	if !workspace.At(root, "").IsJournalDriven() {
		return ""
	}

	relative := ".journal/plugins/" + workspace.JournalPlugin + "/" + Release
	if !present(root, relative) {
		return ""
	}

	return relative
}

// Here is the invocation for the project the working folder is in.
func Here() string {
	cwd, _ := os.Getwd()

	return Invocation(workspace.ProjectRoot(cwd))
}
