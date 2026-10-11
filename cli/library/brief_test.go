package library

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

// TestTheInjectedBriefNamesNoDisciplineButWhereToFindThem holds the block written into a project's instruction
// files to a pointer rather than a catalogue: it carries the one rule every discipline defers to and how a sin is
// found, names the map skill for the rest, and lists no discipline of its own — the list is one load away, and an
// instruction file long enough to be skimmed teaches nothing. The map skill still carries them all.
func TestTheInjectedBriefNamesNoDisciplineButWhereToFindThem(t *testing.T) {
	project := t.TempDir()
	brief := Brief(project, config.Config{})

	for _, want := range []string{"TRACE TO THE SOURCE", "judge src", "info <sin>", "Load `commandments` for the index"} {
		if !strings.Contains(brief, want) {
			t.Errorf("the brief leaves out %q, which must be read before any discipline is", want)
		}
	}

	for _, listed := range []string{"commandments-backend-absence", "commandments-backend-value-objects", "commandments-frontend-vue-components"} {
		if strings.Contains(brief, listed) {
			t.Errorf("the brief lists the discipline %s; the catalogue belongs in the map skill", listed)
		}
	}

	if lines := strings.Count(strings.TrimSpace(brief), "\n") + 1; lines > 40 {
		t.Errorf("the brief runs to %d lines; it is injected into every agent's instructions and must stay short", lines)
	}

	whole := Map(project, config.Config{})
	for _, listed := range []string{"commandments-backend-absence", "commandments-backend-fix-at-the-source"} {
		if !strings.Contains(whole, listed) {
			t.Errorf("the map skill no longer names %s, so the brief points at nothing", listed)
		}
	}
}
