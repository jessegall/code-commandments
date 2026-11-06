package parity

import "regexp"

func EquateInvocation(c Case, repo string, want, got Result) (Result, Result) {
	want.Stdout, got.Stdout = equate(want.Stdout), equate(got.Stdout)
	want.Stderr, got.Stderr = equate(want.Stderr), equate(got.Stderr)
	want.Files, got.Files = equate(want.Files), equate(got.Files)

	return want, got
}

func equate(answer string) string {
	return named.ReplaceAllString(answer, "${1}commandments ")
}

var named = regexp.MustCompile(`(^|[^\w/.-])(?:vendor/bin/|bin/)?commandments `)
