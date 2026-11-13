package scan

import (
	"encoding/json"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
)

// A language's files read in shares, each by a bridge process of its own, write what one process reading them all
// writes: a file's tree must not depend on which other files were asked beside it.
func TestFilesReadInSharesWriteWhatOneReadWrites(t *testing.T) {
	for _, fixture := range []string{"backend", "frontend", "python", "csharp", "go"} {
		t.Run(fixture, func(t *testing.T) {
			root, err := filepath.Abs(filepath.Join("..", "..", "tests", "Fixtures", fixture))
			if err != nil {
				t.Fatal(err)
			}
			sources := Walk([]string{root}, source.Excluded{})

			t.Setenv("XDG_CACHE_HOME", t.TempDir())
			whole := streamsOf(t, sources.ReadingAtOnce(1))
			t.Setenv("XDG_CACHE_HOME", t.TempDir())
			shared := streamsOf(t, sources.ReadingAtOnce(3))
			if len(whole) == 0 {
				t.Skip("no bridge could read this fixture here")
			}
			for file, line := range whole {
				if shared[file] != line {
					t.Errorf("%s is written otherwise when its language is read in shares", file)
				}
			}
			if len(shared) != len(whole) {
				t.Errorf("a read in shares wrote %d files, one read %d", len(shared), len(whole))
			}
		})
	}
}

func streamsOf(t *testing.T, sources Sources) map[string]string {
	t.Helper()
	written := map[string]string{}
	for _, answer := range sources.bridge() {
		if answer.err != nil || answer.stream == nil {
			continue
		}
		for _, file := range answer.stream.Files {
			line, err := json.Marshal(file)
			if err != nil {
				t.Fatal(err)
			}
			written[file.Path] = string(line)
		}
	}

	return written
}
