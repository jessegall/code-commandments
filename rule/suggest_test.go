package rule

import "testing"

func TestEditDistanceCountsTheLettersToChange(t *testing.T) {
	for _, each := range []struct {
		from, to string
		distance int
	}{
		{"", "", 0}, {"is", "is", 0}, {"", "abc", 3}, {"nameLke", "nameLike", 1}, {"fucntion", "function", 2}, {"kitten", "sitting", 3},
	} {
		if got := editDistance(each.from, each.to); got != each.distance {
			t.Errorf("editDistance(%q, %q) = %d, want %d", each.from, each.to, got, each.distance)
		}
	}
}

func TestOnlyANearNameIsSuggested(t *testing.T) {
	options := []string{"parent", "enclosingFunction", "root"}

	for word, hint := range map[string]string{
		"parnt":            ` — did you mean "parent"?`,
		"PARENT":           ` — did you mean "parent"?`,
		"enclosingFuncton": ` — did you mean "enclosingFunction"?`,
		"sideways":         "",
		"":                 "",
	} {
		if got := didYouMean(word, options); got != hint {
			t.Errorf("didYouMean(%q) = %q, want %q", word, got, hint)
		}
	}
}
