package backend

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

// scribeCase is one scribe run as the PHP tool's ScribeTestCase runs it: its detector over one file, the findings
// handed to the scribe.
type scribeCase struct {
	detector detectors.Detector
	scribe   scribes.Scribe
}

// codebaseOf is the codebase one PHP source reads as.
func codebaseOf(t *testing.T, source string) *engine.Codebase {
	t.Helper()
	path := filepath.Join(t.TempDir(), "Subject.php")
	if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}
	codebase, err := php.Here().Scan(path)
	if err != nil {
		t.Fatal(err)
	}

	return codebase
}

// fix is the source with the scribe's rewrite applied, or the source itself when it rewrote nothing.
func (c scribeCase) fix(t *testing.T, source string) string {
	t.Helper()
	rewrites := c.rewrites(t, source)
	if rewrites.Len() == 0 {
		return source
	}

	return rewrites.Content(rewrites.Paths()[0])
}

func (c scribeCase) findings(t *testing.T, source string) []engine.Match {
	t.Helper()

	return c.detector.Find(codebaseOf(t, source))
}

func (c scribeCase) rewrites(t *testing.T, source string) scribes.Rewrites {
	t.Helper()
	codebase := codebaseOf(t, source)
	rewrites, err := c.scribe.Rewrite(c.detector.Find(codebase), codebase)
	if err != nil {
		t.Fatal(err)
	}

	return rewrites
}
