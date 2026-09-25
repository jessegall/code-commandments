package parity

import "regexp"

// makeEntry is make's entry in the overview, its summary wrapped over as many lines as it takes.
var makeEntry = regexp.MustCompile(`(?m)^  make +.*\n(?: {19}.*\n)*`)

// sameMake is what either tool's make entry reads as once equated.
const sameMake = "  make             <scaffold a commandment of the project's own>\n"

// EquateMake makes make's overview entry one in both runs: make scaffolds the custom-rule mechanism, which
// the binary writes as rule files where the PHP tool wrote classes, so its summary differs by design. Every
// other line of the overview stays exact.
func EquateMake(want, got Result) (Result, Result) {
	for _, result := range []*Result{&want, &got} {
		result.Stdout = makeEntry.ReplaceAllString(result.Stdout, sameMake)
		result.Stderr = makeEntry.ReplaceAllString(result.Stderr, sameMake)
	}

	return want, got
}
