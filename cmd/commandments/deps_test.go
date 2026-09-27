package main

import (
	"os/exec"
	"slices"
	"strings"
	"testing"
)

// TestTheToolCarriesNoTestingPackage holds the shipped binary to linking none of Go's testing package: a test helper
// belongs behind a _test.go file or a package only tests import, never in one the tool imports.
func TestTheToolCarriesNoTestingPackage(t *testing.T) {
	out, err := exec.Command("go", "list", "-deps", ".").Output()
	if err != nil {
		t.Fatal(err)
	}
	dependencies := strings.Fields(string(out))
	if slices.Contains(dependencies, "testing") {
		t.Error("the tool links Go's testing package; find the importer with go list -deps and move the helper behind a test")
	}
}
