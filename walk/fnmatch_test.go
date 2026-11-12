package walk

import (
	"encoding/json"
	"os"
	"testing"
)

func TestAGlobMatchesAsPhpFnmatchDoes(t *testing.T) {
	raw, err := os.ReadFile("testdata/fnmatch.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases []struct {
		Pattern  string `json:"pattern"`
		Name     string `json:"name"`
		Pathname bool   `json:"pathname"`
		Want     bool   `json:"want"`
	}
	json.Unmarshal(raw, &cases)

	for _, c := range cases {
		if got := fnmatch(c.Pattern, c.Name, c.Pathname); got != c.Want {
			t.Errorf("fnmatch(%q, %q, %v) = %v", c.Pattern, c.Name, c.Pathname, got)
		}
	}
}
