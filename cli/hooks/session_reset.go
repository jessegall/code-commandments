package hooks

import (
	"os"
	"path/filepath"
	"slices"
)

var freshSources = []string{"startup", "clear"}

// SessionReset clears the hook counters and prunes long-abandoned session folders when a session starts fresh.
type SessionReset struct{}

func (SessionReset) Class() string { return "SessionReset" }
func (SessionReset) Summary() string {
	return "On a fresh session (startup/clear) wipes lingering hook counters and prunes stale session folders."
}
func (SessionReset) Bindings() []Binding { return []Binding{{"SessionStart", ""}} }

func (SessionReset) Handle(event Event) Response {
	if event.Name() != "SessionStart" || !slices.Contains(freshSources, event.Source()) {
		return Silent()
	}

	space := event.Workspace()
	counters, _ := filepath.Glob(space.Path(".*-count"))

	for _, counter := range counters {
		os.Remove(counter)
	}

	space.Prune(0)

	return Silent()
}
