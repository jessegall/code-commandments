package python_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/python"
)

// groups is the keys that share a fingerprint, each group sorted, the groups sorted: what two engines agree on
// when their fingerprints themselves differ.
func groups(fingerprints map[string]string) []string {
	byHash := map[string][]string{}
	for key, hash := range fingerprints {
		byHash[hash] = append(byHash[hash], key)
	}
	var shared []string
	for _, keys := range byHash {
		if len(keys) > 1 {
			slices.Sort(keys)
			shared = append(shared, strings.Join(keys, " = "))
		}
	}
	slices.Sort(shared)

	return shared
}

func TestBodiesHashAlikeAndWeighAsThePHPEngineSays(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string]struct {
		Of         string `json:"of"`
		Normalized string `json:"normalized"`
		Weight     int    `json:"weight"`
	}
	golden(t, "hashes", &want)
	of, shape, wantOf, wantShape := map[string]string{}, map[string]string{}, map[string]string{}, map[string]string{}
	for _, match := range codebase.WhereFunction().Get() {
		def := python.Node{Match: match}
		key := match.Node().Symbol + "@" + place(def)
		answer := want[key]
		of[key], shape[key], wantOf[key], wantShape[key] = def.BodyHash(), def.ShapeHash(), answer.Of, answer.Normalized
		if def.BodyWeight() != answer.Weight {
			t.Errorf("%s weighs %d, not %d", key, def.BodyWeight(), answer.Weight)
		}
	}
	for name, pair := range map[string][2]map[string]string{"bodies": {of, wantOf}, "shapes": {shape, wantShape}} {
		got, answer := groups(pair[0]), groups(pair[1])
		for _, group := range answer {
			if !slices.Contains(got, group) {
				t.Errorf("%s share in PHP but not in Go: %s", name, group)
			}
		}
		for _, group := range got {
			if !slices.Contains(answer, group) {
				t.Errorf("%s share in Go but not in PHP: %s", name, group)
			}
		}
	}
}
