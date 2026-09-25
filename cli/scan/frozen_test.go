package scan

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
)

func TestAFreezeIsReadWherePhpReadsItAndNowhereElse(t *testing.T) {
	raw, err := os.ReadFile("testdata/frozen.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases []struct {
		Path   string `json:"path"`
		Source string `json:"source"`
		Want   bool   `json:"want"`
	}
	json.Unmarshal(raw, &cases)

	for i, c := range cases {
		path := filepath.Join(t.TempDir(), filepath.Base(c.Path))
		if err := os.WriteFile(path, []byte(c.Source), 0o644); err != nil {
			t.Fatal(err)
		}

		if got := IsFrozen(path); got != c.Want {
			t.Errorf("case %d: IsFrozen(%q, %q) = %v", i, c.Path, c.Source, got)
		}
	}
}
