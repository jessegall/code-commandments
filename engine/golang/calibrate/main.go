// Command calibrate runs the Go detectors over a tree outside this project and prints every hit, so each one
// can be read by eye before its rule is published. A rule still being calibrated carries Unpublished(), which
// keeps it out of judge, so this is how its hits are seen at all.
//
//	scripts/dev --mount ../some-app go run ./engine/golang/calibrate ../some-app
//
// It drops every finding in a FROZEN file, which is what the judge's own scope does and what a probe written
// from scratch forgets: one generated .pb.go once accounted for 116 of 160 hits, which is a measurement of the
// generator and not of the rule.
//
// For a rule about nesting it also prints the SHAPE each hit stands in, outermost choice first, with Go's error
// guard named as such. A distribution of shapes says whether a rule is finding one pattern or many, which 300
// file:line pairs do not.
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

	// Every Go detector, published or not, since a published rule's hits are read again whenever a new one
	// changes a reading they share.
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

// shapeOf is the finding's own kind and the kinds of the choices it sits inside, from the outermost in, which
// is what a reader of a nesting rule needs: the SHAPE the hit stands in, so a distribution says whether the
// rule is finding one pattern or many.
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

// guarded names a node by its kind, and an `if` that is Go's error guard by that, since the guard is the one
// shape a Go rule about nesting must never count as a choice of the programmer's own making.
func guarded(held engine.Match) string {
	if (golang.Node{Match: held}).IsErrorCheck() {
		return "errguard"
	}

	return held.Kind()
}

// sortedBy is the shapes, the commonest first.
func sortedBy(shapes map[string]int) []string {
	var names []string
	for name := range shapes {
		names = append(names, name)
	}
	sort.Slice(names, func(a, b int) bool { return shapes[names[a]] > shapes[names[b]] })

	return names
}

// unfrozen drops every finding in a frozen file, as the judge's own scope does: a generated file is read, so
// the types it declares are known, but nothing in it is anybody's to fix. A calibration that counts them is
// measuring the generator.
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
