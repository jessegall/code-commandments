package hooks

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
)

func TestALanguageTheTreeHasNoStreamForIsRefusedInWordsThatNameIt(t *testing.T) {
	for _, told := range []struct {
		language source.Language
		said     []string
	}{
		{source.Python, []string{"has no Python in it", "only the rules that answer from one file"}},
		{source.CSharp, []string{"has no C# in it", "read per checkout rather than per file", "its project's own types are not in that answer"}},
	} {
		why := string(noStreamOf(told.language))

		for _, names := range told.said {
			if !strings.Contains(why, names) {
				t.Errorf("the refusal for %s does not say %q: %s", told.language, names, why)
			}
		}
	}
}

func TestTheRefusalIsSaidAsEverySmallerAnswerIsSaid(t *testing.T) {
	said := Unjudged("judged by the rules that answer from one file", string(noStreamOf(source.CSharp)), "commandments judge --changes")

	for _, names := range []string{"Code Commandments", "because", "not a clean answer, it is a smaller one", "Run `commandments judge --changes`"} {
		if !strings.Contains(said, names) {
			t.Errorf("the refusal is not said in the one wording every smaller answer uses, missing %q: %s", names, said)
		}
	}
}
