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

// bareInvocation is the binary named from the PATH, as it names itself in a project with no composer.json,
// where the PHP tool always names its composer shim.
var bareInvocation = regexp.MustCompile("(^|[\\s`!])commandments ")

// EquateInvocation reads the tool's own name as the composer shim in both runs, so a command the binary
// prints bare in a project with no composer.json is the one the PHP tool prints through the shim.
func EquateInvocation(want, got Result) (Result, Result) {
	for _, result := range []*Result{&want, &got} {
		result.Stdout = bareInvocation.ReplaceAllString(result.Stdout, "${1}vendor/bin/commandments ")
		result.Stderr = bareInvocation.ReplaceAllString(result.Stderr, "${1}vendor/bin/commandments ")
		result.Files = bareInvocation.ReplaceAllString(result.Files, "${1}vendor/bin/commandments ")
	}

	return want, got
}
