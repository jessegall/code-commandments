package parity

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestTheGoBinaryAnswersEveryCaseAsThePhpToolDid(t *testing.T) {
	repo, err := filepath.Abs("../..")
	if err != nil {
		t.Fatal(err)
	}

	binary := filepath.Join(t.TempDir(), "commandments")
	build := exec.Command("go", "build", "-o", binary, "./cmd/commandments")
	build.Dir = repo

	if out, err := build.CombinedOutput(); err != nil {
		t.Fatalf("build: %v\n%s", err, out)
	}

	cases, err := Cases(filepath.Join(repo, CasesDir))
	if err != nil {
		t.Fatal(err)
	}

	for _, c := range cases {
		t.Run(c.Name, func(t *testing.T) {
			t.Parallel()

			raw, err := os.ReadFile(filepath.Join(repo, GoldenFile(c)))
			if err != nil {
				t.Fatalf("no golden; record it with `scripts/dev go run ./cli/parity/record %s`", c.Name)
			}

			want, err := ReadGolden(string(raw))
			if err != nil {
				t.Fatal(err)
			}

			got, err := Run(c, repo, t.TempDir(), binary)
			if err != nil {
				t.Fatal(err)
			}

			want, got = EquateConfigs(want, got, t.TempDir())
			want, got = EquateInvocation(c, repo, want, got)
			matches := got == want

			if c.Pending != "" && matches {
				t.Fatalf("matches the PHP tool now: drop its pending mark (%s)", c.Pending)
			}

			if c.Pending != "" {
				t.Skipf("pending: %s", c.Pending)
			}

			if !matches {
				t.Errorf("differs from the PHP tool in %s\n--- PHP\n%s\n--- Go\n%s", differing(want, got), Golden(want), Golden(got))
			}
		})
	}
}

// differing names each field the two results disagree on, and where a field's text differs it quotes the first
// bytes that part, so a difference the halves below cannot show is still readable.
//
// The halves are printed through the testing package, which indents every line of a message: a blank line and a
// line of spaces read the same there, so a trailing-whitespace difference looks like no difference at all.
func differing(want, got Result) string {
	var said []string

	if want.Exit != got.Exit {
		said = append(said, fmt.Sprintf("exit (%d against %d)", want.Exit, got.Exit))
	}

	for _, field := range []struct {
		name        string
		want_, got_ string
	}{
		{"stdout", want.Stdout, got.Stdout},
		{"stderr", want.Stderr, got.Stderr},
		{"files", want.Files, got.Files},
	} {
		if field.want_ == field.got_ {
			continue
		}

		said = append(said, field.name+" "+parts(field.want_, field.got_))
	}

	if len(said) == 0 {
		return "nothing this comparison can name"
	}

	return strings.Join(said, ", ")
}

// parts is where two texts part, as bytes: the first that differs with what surrounds it, or the tail one has and
// the other does not.
func parts(want, got string) string {
	for at := range min(len(want), len(got)) {
		if want[at] != got[at] {
			return fmt.Sprintf("from byte %d, %q against %q", at, cut(want[at:]), cut(got[at:]))
		}
	}

	if len(want) > len(got) {
		return fmt.Sprintf("with %d byte(s) more, %q", len(want)-len(got), cut(want[len(got):]))
	}

	return fmt.Sprintf("with %d byte(s) fewer, the run having %q", len(got)-len(want), cut(got[len(want):]))
}

func cut(text string) string {
	if len(text) > 60 {
		return text[:60] + "…"
	}

	return text
}

func TestAGoldenReadsBackAsTheResultItWasWrittenFrom(t *testing.T) {
	result := Result{Exit: 2, Stdout: "a\n\nb\n", Stderr: "✗ c\n", Files: "=== created x\ny\n"}

	if back, err := ReadGolden(Golden(result)); err != nil || back != result {
		t.Errorf("round trip: %+v, %v", back, err)
	}
}
