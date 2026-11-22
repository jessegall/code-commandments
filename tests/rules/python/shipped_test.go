package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/rules"
)

// shipped is the shipped rule judging Python whose sin has the name.
func shipped(t *testing.T, name string) rule.Detector {
	t.Helper()
	for _, each := range rules.Of(catalog.Python) {
		if each.Sin().Definition().Name == name {
			return each
		}
	}
	t.Fatalf("no shipped rule %s judges Python", name)

	return nil
}
