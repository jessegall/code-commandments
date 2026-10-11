package parity

import "regexp"

// EquateInvocation reads every naming of the executable, in both answers, as the same word. Which file a command
// names is cli/binary's answer, given per project: the PHP tool never asked it — it printed its composer shim in
// most of its prose and a bare `commandments` in the rest, so one run named two different files — while the binary
// resolves one invocation for the project and prints that throughout. Parity guards what a command SAYS, and
// cli/binary's own tests guard which file it names, so the name itself is read as one word here.
func EquateInvocation(c Case, repo string, want, got Result) (Result, Result) {
	want.Stdout, got.Stdout = equate(want.Stdout), equate(got.Stdout)
	want.Stderr, got.Stderr = equate(want.Stderr), equate(got.Stderr)
	want.Files, got.Files = equate(want.Files), equate(got.Files)

	return want, got
}

// equate writes every naming of the executable in one answer as the bare name.
func equate(answer string) string {
	return named.ReplaceAllString(answer, "${1}commandments ")
}

// named matches the executable wherever either tool named it: bare, through the composer shim, or from a checkout's
// own bin/. The character before it may be neither a word character nor `/`, `-` or `.`, so a path, a mention of
// `code-commandments` and the `.commandments` folder are left alone.
var named = regexp.MustCompile(`(^|[^\w/.-])(?:vendor/bin/|bin/)?commandments `)
