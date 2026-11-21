package typescript

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rules"
)

// shipped is the shipped rule judging TypeScript whose sin has the name.
func shipped(t *testing.T, name string) detectors.Detector {
	t.Helper()
	for _, each := range rules.Of(catalog.TypeScript) {
		if each.Sin().Definition().Name == name {
			return each
		}
	}
	t.Fatalf("no shipped rule %s judges TypeScript", name)

	return nil
}
