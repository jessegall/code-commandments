package natural

import (
	"encoding/json"
	"os"
	"testing"
)

func TestCompareOrdersAsPhpStrnatcmpDoes(t *testing.T) {
	raw, err := os.ReadFile("testdata/strnatcmp.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases [][3]any
	json.Unmarshal(raw, &cases)

	for _, c := range cases {
		a, b, want := c[0].(string), c[1].(string), int(c[2].(float64))

		if got := Compare(a, b); got != want {
			t.Errorf("Compare(%q, %q) = %d, PHP says %d", a, b, got, want)
		}
	}
}
