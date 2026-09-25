// Command enrol writes registry/registry.go from the rule folders present: go generate ./registry.
package main

import (
	"fmt"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/catalog"
)

func main() {
	if len(os.Args) != 2 {
		fmt.Fprintln(os.Stderr, "usage: enrol <module root>")
		os.Exit(2)
	}
	root := os.Args[1]
	source, err := catalog.Enrolment(root)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	if err := os.WriteFile(filepath.Join(root, "registry", "registry.go"), source, 0o644); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}
