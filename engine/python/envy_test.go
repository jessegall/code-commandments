package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python"
)

func TestEveryMethodEnviesWhatThePHPEngineSays(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string]struct {
		Envies *string `json:"envies"`
		Lookup bool    `json:"lookup"`
	}
	golden(t, "envy", &want)
	for _, match := range codebase.WhereFunction().Get() {
		def := python.Node{Match: match}
		key := match.Node().Symbol + "@" + place(def)
		answer := want[key]
		envied, envies := codebase.Program.EnviedParameter(def)
		if envies != (answer.Envies != nil) || (envies && envied != *answer.Envies) {
			t.Errorf("%s envies %q (%v), not %v", key, envied, envies, answer.Envies)
		}
		if codebase.Program.IsLookupEnvious(def) != answer.Lookup {
			t.Errorf("%s is lookup-envious: %v", key, !answer.Lookup)
		}
	}
}
