// Command findings runs every backend detector over a PHP project, its frontend read beside it as judge reads it,
// and prints what each finds, to hold the Go port to the PHP tool on a real codebase: go run ./engine/php/cmd/findings [--spans] [--every] [--only=NAME] <project> [path...]
//
// Each line is file:line, the detector and its sin, sorted. With --spans each is the file, the span and the kind of
// the node flagged, and the detector, which is what the PHP oracle's findings question records.
package main

import (
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
	_ "github.com/jessegall/code-commandments/registry"
)

func main() {
	spans := flag.Bool("spans", false, "print each finding's span and kind instead of its line")
	every := flag.Bool("every", false, "run the unpublished detectors too")
	only := flag.String("only", "", "run only the named detector")
	flag.Parse()
	if flag.NArg() == 0 {
		fmt.Fprintln(os.Stderr, "usage: findings [--spans] <project> [path...]")
		os.Exit(2)
	}
	root, err := filepath.Abs(flag.Arg(0))
	if err != nil {
		fail(err)
	}
	paths := flag.Args()[1:]
	if len(paths) == 0 {
		paths = []string{root}
	}
	backend, err := php.Here().Stream(paths...)
	if err != nil {
		fail(err)
	}
	frontend, err := frontend.Here().Stream(paths...)
	if err != nil {
		fail(err)
	}
	codebase := engine.Load(backend, frontend)
	php.TypesOf(codebase).Fill(codebase)
	var lines []string
	for _, detector := range InForce(root, *every, *only) {
		for _, finding := range detector.Find(codebase) {
			lines = append(lines, line(root, catalog.Name(detector), detector, finding, *spans))
		}
	}
	slices.Sort(lines)
	fmt.Println(strings.Join(lines, "\n"))
}

// InForce is every published backend detector whose sin's package the project installs; every includes the
// unpublished ones, and only narrows it to one detector by name.
func InForce(root string, every bool, only string) []detectors.Detector {
	candidates := detectors.Of(catalog.Backend)
	if every {
		candidates = detectors.Every(catalog.Backend)
	}
	if only != "" {
		named, found := detectors.NamedIn(candidates, only)
		if !found {
			return nil
		}
		candidates = []detectors.Detector{named}
	}
	var inForce []detectors.Detector
	for _, detector := range candidates {
		requires := detector.Sin().Definition().Requires
		if requires.Name == "" || requires.InstalledIn(root) {
			inForce = append(inForce, detector)
		}
	}

	return inForce
}

func line(root, name string, detector detectors.Detector, finding engine.Match, spans bool) string {
	file, err := filepath.Rel(root, finding.File())
	if err != nil {
		file = finding.File()
	}
	if spans {
		node := finding.Node()

		return fmt.Sprintf("%s [%d %d] %s\t%s", file, node.Span.Start, node.Span.End, node.Kind, name)
	}

	return fmt.Sprintf("%s:%d\t%s\t%s", file, finding.Line(), name, detector.Sin().Definition().Name)
}

func fail(err error) {
	fmt.Fprintln(os.Stderr, err)
	os.Exit(1)
}
