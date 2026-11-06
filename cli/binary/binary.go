// Package binary says where a project's commandments executable is, so every command written into the project (a wired hook, the composer sync call) names a file that is really there.
package binary

import (
	"os"
	"slices"

	"github.com/jessegall/code-commandments/cli/workspace"
)

var candidates = []string{Shim, "bin/commandments"}

const Name = "commandments"

func present(root, path string) bool {
	info, err := os.Stat(root + "/" + path)

	return err == nil && info.Mode().IsRegular()
}

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

func In(root string) string {
	for _, candidate := range candidates {
		if present(root, candidate) {
			return candidate
		}
	}

	return candidates[0]
}

func Launcher(root string) string {
	if In(root) == Shim {
		return "vendor/jessegall/code-commandments/bin/hook"
	}

	return "bin/hook"
}

const Release = "bin/commandments-release"

const Shim = "vendor/bin/commandments"

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

func ThroughComposer(root string) bool {
	return slices.Contains(candidates, Invocation(root))
}

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

const Alias = "journal sins"

func Judging(root string) string {
	if ThroughPlugin(root) != "" {
		return Alias
	}

	return Invocation(root) + " judge"
}

func Here() string {
	cwd, _ := os.Getwd()

	return Invocation(workspace.ProjectRoot(cwd))
}
