package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rules"
)

// shipped is the shipped rule judging CSharp whose sin has the name.
func shipped(t *testing.T, name string) detectors.Detector {
	t.Helper()
	for _, each := range rules.Of(catalog.CSharp) {
		if each.Sin().Definition().Name == name {
			return each
		}
	}
	t.Fatalf("no shipped rule %s judges CSharp", name)

	return nil
}
