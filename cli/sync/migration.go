package sync

import (
	"os"
	"path/filepath"
	"strconv"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// format is the state-file format a project is brought up to; a project already there is left alone.
const format = 5

// checklists is the folder a session keeps its judge checklists in.
const checklists = "sins"

// formatLegend says what the format stamp is for.
var formatLegend = &state.Legend{
	About: "Which format code-commandments writes its session state files in. It exists so an upgrade " +
		"can convert what is already on disk exactly once.",
	Variables: []state.Variable{{Name: "format", Meaning: "the state-file format this project has been brought up to"}},
	Defaults:  state.New(state.Int("format", 0)),
	Safe:      "the conversion simply runs again on the next `composer update`",
}

// Migrate carries the project's session state across a format change, once: session folders move to where
// they live now, what still has a reader is moved into place, and the files of a removed feature go. It
// answers what it did, a line each.
func Migrate(space workspace.Workspace) []string {
	var done []string

	if relocated := space.RelocateSessions(); relocated > 0 {
		rel, _ := filepath.Rel(space.Root(), space.SessionsDir())
		done = append(done, strconv.Itoa(relocated)+" session folder(s) moved to "+rel)
	}

	stamp := state.At(space.Cache(".state-format"), formatLegend)

	if stamp.Read().Int("format", 0) >= format {
		return done
	}

	sessions, _ := filepath.Glob(filepath.Join(space.SessionsDir(), "*"))

	for _, dir := range sessions {
		if info, err := os.Stat(dir); err == nil && info.IsDir() {
			done = append(done, session(dir)...)
		}
	}

	stamp.Write(state.New(state.Int("format", format)))

	return done
}

func session(dir string) []string {
	var done []string

	for _, step := range []func(string) string{gate, plan, moveChecklists, counters} {
		if line := step(dir); line != "" {
			done = append(done, line)
		}
	}

	return done
}

// gate removes the files of the stop gate, which the agent journal replaced.
func gate(dir string) string {
	return removed(dir, "stop-gate file(s) removed", ".until*")
}

// plan removes the files of plan execution, which is gone.
func plan(dir string) string {
	return removed(dir, "plan file(s) removed", ".plan-*", ".constraints-verified")
}

// counters resets every hook counter, whose format changed.
func counters(dir string) string {
	return removed(dir, "hook counter(s) reset", ".*-count")
}

func removed(dir, what string, patterns ...string) string {
	count := 0

	for _, pattern := range patterns {
		files, _ := filepath.Glob(filepath.Join(dir, pattern))

		for _, file := range files {
			os.Remove(file)
			count++
		}
	}

	if count == 0 {
		return ""
	}

	return strconv.Itoa(count) + " " + what
}

// moveChecklists moves the judge checklists a session kept loose into its checklist folder; one already
// there is not overwritten, and with no folder to put them in they stay where they are.
func moveChecklists(dir string) string {
	loose, _ := filepath.Glob(filepath.Join(dir, "sins.md"))
	numbered, _ := filepath.Glob(filepath.Join(dir, "sins-*.md"))
	strays := append(loose, numbered...)

	if len(strays) == 0 {
		return ""
	}

	folder := filepath.Join(dir, checklists)
	if err := os.MkdirAll(folder, 0o755); err != nil {
		return ""
	}

	moved := 0

	for _, stray := range strays {
		target := filepath.Join(folder, filepath.Base(stray))

		if _, err := os.Stat(target); err != nil && os.Rename(stray, target) == nil {
			moved++
		}
	}

	if moved == 0 {
		return ""
	}

	return strconv.Itoa(moved) + " judge checklist(s) moved into " + checklists + "/"
}
