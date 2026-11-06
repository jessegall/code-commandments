package library

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func TestTheInjectedBriefNamesNoDisciplineButWhereToFindThem(t *testing.T) {
	project := t.TempDir()
	brief := Brief(project, config.Config{})

	for _, want := range []string{"TRACE TO THE SOURCE", "judge` ONCE, scoped", "info <sin>", "Load `commandments` for the index"} {
		if !strings.Contains(brief, want) {
			t.Errorf("the brief leaves out %q, which must be read before any discipline is", want)
		}
	}

	for _, listed := range []string{"commandments-php-absence", "commandments-php-value-objects", "commandments-vue-vue-components"} {
		if strings.Contains(brief, listed) {
			t.Errorf("the brief lists the discipline %s; the catalogue belongs in the map skill", listed)
		}
	}

	if lines := strings.Count(strings.TrimSpace(brief), "\n") + 1; lines > 40 {
		t.Errorf("the brief runs to %d lines; it is injected into every agent's instructions and must stay short", lines)
	}

	whole := Map(project, config.Config{})
	for _, listed := range []string{"commandments-php-absence", "commandments-php-fix-at-the-source"} {
		if !strings.Contains(whole, listed) {
			t.Errorf("the map skill no longer names %s, so the brief points at nothing", listed)
		}
	}
}
