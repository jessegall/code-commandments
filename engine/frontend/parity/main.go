// Command parity prints what the Go frontend detectors find under a path, one `file:line [Detector]` a line,
// sorted: the form scripts/frontend-parity.sh compares against the PHP tool's checklist.
package main

import (
	"fmt"
	"os"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/frontend"
	_ "github.com/jessegall/code-commandments/registry"
)

func main() {
	if len(os.Args) != 2 {
		fmt.Fprintln(os.Stderr, "usage: parity <path>")
		os.Exit(2)
	}
	codebase, err := frontend.Here().Scan(os.Args[1])
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	var lines []string
	for _, detector := range append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...) {
		for _, finding := range detector.Find(codebase) {
			lines = append(lines, fmt.Sprintf("%s [%s]", finding.Location(), catalog.Name(detector)))
		}
	}
	slices.Sort(lines)
	for _, line := range slices.Compact(lines) {
		fmt.Println(line)
	}
}
