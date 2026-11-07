package hooks

import "testing"

func TestAnEventArrivingUnderEitherJournalsSpellingIsTheSameEvent(t *testing.T) {
	for arriving, meant := range map[string]string{
		"plugin.update":         updatedEvent,
		"plugin.updated":        updatedEvent,
		"worker_worktree.merge": landedEvent,
		"suites.coding.workers.worker_worktree.merge": landedEvent,
		"file_edited":               "file_edited",
		"something.nobody.declared": "something.nobody.declared",
	} {
		if said := namedAsTheKey(arriving); said != meant {
			t.Errorf("an event arriving as %q reads as %q, where it is %q; version 4 sends the name a manifest "+
				"declares and version 5 sends the key of the row that fired, and both are one event here",
				arriving, said, meant)
		}
	}
}
