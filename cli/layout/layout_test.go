package layout

import (
	"encoding/json"
	"os"
	"testing"
)

func TestWrapBreaksExactlyWherePhpWordwrapDoes(t *testing.T) {
	raw, err := os.ReadFile("testdata/wordwrap.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases []struct {
		Text  string `json:"text"`
		Width int    `json:"width"`
		Break string `json:"break"`
		Want  string `json:"want"`
	}
	if err := json.Unmarshal(raw, &cases); err != nil {
		t.Fatal(err)
	}

	for i, c := range cases {
		if got := Wrap(c.Text, c.Width, c.Break); got != c.Want {
			t.Errorf("case %d: Wrap(%q, %d, %q)\n got %q\nwant %q", i, c.Text, c.Width, c.Break, got, c.Want)
		}
	}
}

func TestPadCountsCharactersNotBytes(t *testing.T) {
	if got := Pad("—x", 4); got != "—x  " {
		t.Errorf("Pad = %q", got)
	}
}
