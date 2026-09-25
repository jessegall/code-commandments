// Package binary says where a project's commandments executable is, so every command written into the
// project (a wired hook, the composer sync call) names a file that is really there.
package binary

import "os"

// candidates are where the executable lives: a consumer's composer shim, else a checkout's own bin/.
var candidates = []string{"vendor/bin/commandments", "bin/commandments"}

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
