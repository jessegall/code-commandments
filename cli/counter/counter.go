// Package counter is a named count a session keeps in its folder, for a nudge that fires the first time and
// then every so often.
package counter

import (
	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Counter is one named count.
type Counter struct {
	file  state.File
	every int
}

// Named is the counter slug in the session's folder, described for whoever opens its file.
func Named(space workspace.Workspace, slug, describe string, every int) Counter {
	meaning := "the running count"
	if describe != "" {
		meaning += ". It " + describe
	}

	legend := &state.Legend{
		About:     "Code-commandments counter `" + slug + "` — a hook heartbeat.",
		Variables: []state.Variable{{Name: "count", Meaning: meaning}},
		Defaults:  state.New(state.Int("count", 0)),
	}

	return Counter{state.At(space.Path("."+slug+"-count"), legend), every}
}

// Count is the count as it stands.
func (c Counter) Count() int {
	return c.file.Read().Int("count", 0)
}

// Bump adds one and answers the new count.
func (c Counter) Bump() (int, error) {
	count := c.Count() + 1

	return count, c.file.Write(state.New(state.Int("count", count)))
}

// FirstThenEvery bumps the count and says whether this is the first time or an every-th one.
func (c Counter) FirstThenEvery() (bool, error) {
	count, err := c.Bump()

	return count == 1 || c.every > 0 && count%c.every == 0, err
}

// Due bumps the count and says whether it reached every, starting over when it did.
func (c Counter) Due() bool {
	if count, _ := c.Bump(); count < c.every {
		return false
	}

	c.Reset()

	return true
}

// Reset starts the count over.
func (c Counter) Reset() {
	c.file.Write(state.New(state.Int("count", 0)))
}

// MovedBy says whether work has moved on by at least stretch since the count last marked it, marking it
// when it has. The mark is held one past the work, so "never marked" and "marked before any work" differ.
func (c Counter) MovedBy(work, stretch int) bool {
	if marked := c.Count(); marked > 0 && work-(marked-1) < stretch {
		return false
	}

	c.file.Write(state.New(state.Int("count", work+1)))

	return true
}
