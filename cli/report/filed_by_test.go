package report

import (
	"strings"
	"testing"
)

// TestAFiledReportNamesTheReleaseItRan holds every issue the tool files to stating the release that filed it, so a
// report is read against the code that made it.
func TestAFiledReportNamesTheReleaseItRan(t *testing.T) {
	for _, verb := range []string{"report", "feature-request"} {
		if footer := filedBy(verb, "v5.16.1"); !strings.Contains(footer, "code-commandments v5.16.1") || !strings.Contains(footer, "`commandments "+verb+"`") {
			t.Errorf("%s closes with %q", verb, footer)
		}
	}
}
