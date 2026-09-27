package parity

import "strings"

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
