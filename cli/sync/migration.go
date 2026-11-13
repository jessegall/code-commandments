package sync

import (
	"os"
	"path/filepath"
	"strconv"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

const format = 5

const checklists = "sins"

var formatLegend = &state.Legend{
	About: "Which format code-commandments writes its session state files in. It exists so an upgrade " +
		"can convert what is already on disk exactly once.",
	Variables: []state.Variable{{Name: "format", Meaning: "the state-file format this project has been brought up to"}},
	Defaults:  state.New(state.Int("format", 0)),
	Safe:      "the conversion simply runs again on the next `composer update`",
}

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
		if walk.IsFolder(dir) {
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

func gate(dir string) string {
	return removed(dir, "stop-gate file(s) removed", ".until*")
}

func plan(dir string) string {
	return removed(dir, "plan file(s) removed", ".plan-*", ".constraints-verified")
}

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

		if !walk.Exists(target) && os.Rename(stray, target) == nil {
			moved++
		}
	}

	if moved == 0 {
		return ""
	}

	return strconv.Itoa(moved) + " judge checklist(s) moved into " + checklists + "/"
}
