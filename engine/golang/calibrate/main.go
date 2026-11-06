// Command calibrate runs the Go detectors over a tree outside this project and prints every hit, so each one can be read by eye before its rule is published.
package main

import (
	"fmt"
	"os"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	godetectors "github.com/jessegall/code-commandments/detectors/go"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
)

func main() {
	root := os.Args[1]
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}

	calibrating := []detectors.Detector{
		godetectors.DeepNestingDetector{},
		godetectors.RedundantElseDetector{},
		godetectors.LoopWrappedInIfDetector{},
	}
	for _, detector := range calibrating {
		found := unfrozen(detector.Find(codebase))
		sort.Slice(found, func(a, b int) bool { return found[a].Location() < found[b].Location() })
		fmt.Printf("\n== %s: %d hits\n", detector.Sin().Definition().Name, len(found))
		shapes := map[string]int{}
		for _, held := range found {
			fmt.Printf("%s\t%s\n", held.Location(), shapeOf(held))
			shapes[shapeOf(held)]++
		}
		for _, shape := range sortedBy(shapes) {
			fmt.Printf("  %4d  %s\n", shapes[shape], shape)
		}
	}
	fmt.Printf("\n%d files read\n", len(codebase.Files()))
}

func shapeOf(held engine.Match) string {
	var above []string
	for _, held := range held.Ancestors() {
		if held.Is(engine.Function) {
			break
		}
		if (golang.Node{Match: held}).IsChoice() {
			above = append(above, guarded(held))
		}
	}
	slices.Reverse(above)

	return strings.Join(append(above, guarded(held)), " > ")
}

func guarded(held engine.Match) string {
	if (golang.Node{Match: held}).IsErrorCheck() {
		return "errguard"
	}

	return held.Kind()
}

func sortedBy(shapes map[string]int) []string {
	var names []string
	for name := range shapes {
		names = append(names, name)
	}
	sort.Slice(names, func(a, b int) bool { return shapes[names[a]] > shapes[names[b]] })

	return names
}

func unfrozen(found []engine.Match) []engine.Match {
	var kept []engine.Match
	for _, held := range found {
		if held.Source().IsFrozen() {
			continue
		}
		kept = append(kept, held)
	}

	return kept
}
