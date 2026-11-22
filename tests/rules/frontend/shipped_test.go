package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/rules"
)

// shipped is the shipped rule judging Vue whose sin has the name.
func shipped(t *testing.T, name string) rule.Detector {
	t.Helper()
	for _, each := range rules.Of(catalog.Vue) {
		if each.Sin().Definition().Name == name {
			return each
		}
	}
	t.Fatalf("no shipped rule %s judges Vue", name)

	return nil
}
