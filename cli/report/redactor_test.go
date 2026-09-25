package report

import (
	"encoding/json"
	"os"
	"testing"
)

func TestRedactMasksWhatPhpsRedactorMasks(t *testing.T) {
	raw, err := os.ReadFile("testdata/redact.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases []struct {
		Line string `json:"line"`
		Want string `json:"want"`
	}
	json.Unmarshal(raw, &cases)

	for _, c := range cases {
		if got := Redact(c.Line); got != c.Want {
			t.Errorf("Redact(%q)\n got %q\nwant %q", c.Line, got, c.Want)
		}
	}
}
