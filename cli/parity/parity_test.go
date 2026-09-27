package parity

import (
	"os"
	"os/exec"
	"path/filepath"
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
				t.Errorf("differs from the PHP tool\n--- PHP\n%s\n--- Go\n%s", Golden(want), Golden(got))
			}
		})
	}
}

func TestAGoldenReadsBackAsTheResultItWasWrittenFrom(t *testing.T) {
	result := Result{Exit: 2, Stdout: "a\n\nb\n", Stderr: "✗ c\n", Files: "=== created x\ny\n"}

	if back, err := ReadGolden(Golden(result)); err != nil || back != result {
		t.Errorf("round trip: %+v, %v", back, err)
	}
}
