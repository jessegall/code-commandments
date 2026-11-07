package parity

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestNoGoldenRecordsALanguageThisMachineCouldNotRead(t *testing.T) {
	golden, err := filepath.Glob(filepath.Join("testdata", "golden", "*.golden"))
	if err != nil {
		t.Fatal(err)
	}

	if len(golden) == 0 {
		t.Fatal("no golden was read, so this test would pass by looking at nothing")
	}

	for _, each := range golden {
		if strings.Contains(filepath.Base(each), "without-the-bridge") {
			continue
		}

		said, err := os.ReadFile(each)
		if err != nil {
			t.Fatal(err)
		}

		for _, cannot := range []string{"is not judged; everything else is", "file(s) left unread"} {
			if strings.Contains(string(said), cannot) {
				t.Errorf("%s records %q, which is what a machine missing a bridge says, not what the tool "+
					"answers: a golden recorded that way turns a red test green and hides the language entirely",
					filepath.Base(each), cannot)
			}
		}
	}
}
