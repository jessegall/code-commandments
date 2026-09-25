package cli

import "testing"

func TestIntvalReadsTheLeadingNumberAsPHPDoes(t *testing.T) {
	cases := map[string]int{
		"4": 4, "  7": 7, "3x": 3, "abc": 0, "": 0, "-2": -2, "+5": 5,
		"1.9": 1, "1e3": 1000, ".5": 0, "0x1A": 0, "12 ": 12,
		"99999999999999999999": 9223372036854775807,
	}

	for text, want := range cases {
		if got := Intval(text); got != want {
			t.Errorf("Intval(%q) = %d, want %d", text, got, want)
		}
	}
}
