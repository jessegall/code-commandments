// Package workspace is where the tool keeps what it generates inside a project.
package workspace

import (
	"os"
	"strings"
)

// dir is the folder the tool keeps a project's state in when no journal owns its hooks.
const dir = ".commandments"

// JournalPlugin is the folder the agent journal installs the tool into as a plugin, and names its data folder after.
const JournalPlugin = "code-commandments"

// Workspace is a project's own place for the tool's state.
type Workspace struct {
	root string
}

// At is the workspace of the project at root.
func At(root string) Workspace {
	return Workspace{root: strings.TrimRight(root, "/")}
}

// IsJournalDriven says whether the agent journal owns the project's hooks: its plugin folder is installed beside it.
func (w Workspace) IsJournalDriven() bool {
	info, err := os.Stat(w.root + "/.journal/plugins/" + JournalPlugin + "/.journal-plugin/plugin.json")

	return err == nil && info.Mode().IsRegular()
}

// StateDir is where the state the tool generates lives: the journal's data folder for the plugin when the journal
// drives the project, else the project's own folder.
func (w Workspace) StateDir() string {
	if w.IsJournalDriven() {
		return w.root + "/.journal/plugin-data/" + JournalPlugin
	}

	return w.root + "/" + dir
}

// Cache is a generated file shared by every session, a cache or a stamp, in the state folder.
func (w Workspace) Cache(file string) string {
	return w.StateDir() + "/" + file
}
