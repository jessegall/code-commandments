//go:build story

package engine

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

func TestAStoryReachesThisProjectsOwnJournalAndFindsItsTestEnvironment(t *testing.T) {
	s := story.Begin(t)

	listed := s.Asks("environment", "list")

	if listed.Code != 0 {
		s.Fails("the journal refused to list its environments: " + listed.Err)
	}

	if !strings.Contains(listed.Out, "story-engine") {
		s.Fails("the journal's environments hold no story-engine, the test environment every story runs in:\n" + listed.Out)
	}
}
