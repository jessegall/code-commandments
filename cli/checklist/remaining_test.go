package checklist_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/checklist"
)

// TestTheWorkLeftIsWhatStandsAboveTheReportedSection holds the count the stop reminder nags on. The lines under
// the reported heading are findings this project has reported as wrong, which the checklist itself tells a reader
// not to fix and not to delete, so counting them would nag at every stop for work nobody may do.
func TestTheWorkLeftIsWhatStandsAboveTheReportedSection(t *testing.T) {
	for name, test := range map[string]struct {
		content string
		want    int
	}{
		"work above, reported below": {
			content: "# Code Commandments — 1 sins to fix\n\n## backend/absence\n\n- `src/a.py:3`  f  [A]\n\n" +
				checklist.ReportedHeading + "\n\n- `src/b.py:9`  g  [B] — issue 814\n- `src/c.py:9`  h  [C] — issue 900\n",
			want: 1,
		},
		"nothing but reported lines": {
			content: "# Code Commandments — 0 sins to fix\n\n" + checklist.ReportedHeading +
				"\n\n- `src/b.py:9`  g  [B] — issue 814\n",
			want: 0,
		},
		"no reported section at all": {
			content: "# Code Commandments — 2 sins to fix\n\n## backend/absence\n\n- `src/a.py:3`  f  [A]\n- `src/b.py:4`  g  [B]\n",
			want:    2,
		},
	} {
		t.Run(name, func(t *testing.T) {
			path := filepath.Join(t.TempDir(), "sins.md")
			if err := os.WriteFile(path, []byte(test.content), 0o644); err != nil {
				t.Fatal(err)
			}

			if left := checklist.At(path).RemainingSins(); left != test.want {
				t.Errorf("%d sins are left to work, want %d", left, test.want)
			}
		})
	}
}
