package parity

import (
	"regexp"
	"strings"
)

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

// EquateInvocation reads the composer shim the PHP tool names as the binary from the PATH, in the PHP tool's answer
// only, when the case's project has no composer.json: there the binary names itself bare, where the PHP tool always
// names its shim. The binary's own output is compared as printed, so naming the shim in a project without composer,
// or the bare binary in a project with it, still differs as it should.
func EquateInvocation(c Case, repo string, want, got Result) (Result, Result) {
	if c.HasComposer(repo) {
		return want, got
	}

	want.Stdout = strings.ReplaceAll(want.Stdout, shim, "commandments ")
	want.Stderr = strings.ReplaceAll(want.Stderr, shim, "commandments ")
	want.Files = strings.ReplaceAll(want.Files, shim, "commandments ")

	return want, got
}

// shim is the composer shim as the PHP tool names it in a command.
const shim = "vendor/bin/commandments "
