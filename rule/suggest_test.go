package rule

import (
	"testing"

	"github.com/jessegall/code-commandments/skill"
)

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

func TestTheWordItselfIsNeverTheSuggestion(t *testing.T) {
	options := []string{"description", "parent", "root"}

	if got := didYouMean("description", options); got != "" {
		t.Errorf(`didYouMean("description") = %q, want no hint: the word was refused as it stands`, got)
	}

	if got := didYouMean("Description", options); got != ` — did you mean "description"?` {
		t.Errorf(`didYouMean("Description") = %q, want the hint: a wrong case is a slip worth naming`, got)
	}
}

func TestAMisplacedKeyIsNamedWhereItBelongs(t *testing.T) {
	for key, where := range map[string]string{
		"description": ` — "description" is a key of "sin"`,
		"select":      ` — "select" is a key of "find"`,
		"replace":     ` — "replace" is a key of "fix"`,
		"matches":     ` — "matches" is a key of "substitute"`,
		"engine":      "",
		"nowhere":     "",
	} {
		if got := belongsIn(key); got != where {
			t.Errorf("belongsIn(%q) = %q, want %q", key, got, where)
		}
	}
}

func TestARuleFileSaysWhereAMisplacedKeyBelongs(t *testing.T) {
	written := []byte(`{"engine": "python", "description": "a docstring inside a method", "find": {"select": "function"}, "sin": {"name": "x", "description": "y", "skill": "python/documentation"}}`)

	_, err := Parse("DocstringInMethodDetector", written, skill.Slugged)
	if err == nil {
		t.Fatal("a rule with sin's key at its top was read as if it were whole")
	}

	want := `DocstringInMethodDetector: nothing reads "description" where it stands — "description" is a key of "sin"`
	if err.Error() != want {
		t.Errorf("Parse said %q, want %q", err.Error(), want)
	}
}
