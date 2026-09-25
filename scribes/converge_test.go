package scribes

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"testing"
)

type everywhere struct{}

func (everywhere) Includes(string) bool { return true }
func (everywhere) IsScoped() bool       { return false }

type frozenSet map[string]bool

func (f frozenSet) IsFrozen(path string) bool { return f[path] }

// stepFunc is a step whose run is a function.
type stepFunc struct {
	name string
	run  func(Pass) (Rewrites, error)
}

func (s stepFunc) Name() string                    { return s.name }
func (s stepFunc) Run(pass Pass) (Rewrites, error) { return s.run(pass) }

func rewriting(path, content string) Rewrites {
	rewrites := Rewrites{}
	rewrites.Set(path, content)

	return rewrites
}

func TestTheChainSweepsUntilNothingChangesEachStepReadingTheDraftsBeforeIt(t *testing.T) {
	counts := 0
	count := stepFunc{"count", func(pass Pass) (Rewrites, error) {
		if pass.Drafts.Content("a") == "aaa" {
			return Rewrites{}, nil
		}
		counts++

		return rewriting("a", pass.Drafts.Content("a")+"a"), nil
	}}
	echo := stepFunc{"echo", func(pass Pass) (Rewrites, error) { return rewriting("b", pass.Drafts.Content("a")), nil }}

	converged := Converge((&Chain{}).Append(count).Append(echo), nil, everywhere{}, frozenSet{})

	if !converged.Settled || converged.Files.Content("a") != "aaa" || converged.Files.Content("b") != "aaa" || counts != 3 {
		t.Fatalf("settled %v a %q b %q after %d", converged.Settled, converged.Files.Content("a"), converged.Files.Content("b"), counts)
	}
	if got := converged.Files.Paths(); !slices.Equal(got, []string{"a", "b"}) {
		t.Fatalf("got %v", got)
	}
}

func TestABrokenStepIsDroppedOnceAndTheRestStillRun(t *testing.T) {
	runs := 0
	broken := stepFunc{"broken", func(Pass) (Rewrites, error) { runs++; return Rewrites{}, errors.New("no") }}
	panics := stepFunc{"panics", func(Pass) (Rewrites, error) { panic("boom") }}
	fine := stepFunc{"fine", func(Pass) (Rewrites, error) { return rewriting("a", "fixed"), nil }}

	converged := Converge((&Chain{}).Append(broken).Append(panics).Append(fine), nil, everywhere{}, frozenSet{})

	if runs != 1 || len(converged.Skipped) != 2 || converged.Skipped[0].Step != "broken" || converged.Skipped[1].Err.Error() != "boom" {
		t.Fatalf("ran %d, skipped %v", runs, converged.Skipped)
	}
	if converged.Files.Content("a") != "fixed" {
		t.Fatal("the steps after a broken one still run")
	}
}

func TestARewriteTouchingAFrozenFileIsDroppedWhole(t *testing.T) {
	root := t.TempDir()
	frozen, open := filepath.Join(root, "Frozen.php"), filepath.Join(root, "Open.php")
	for _, path := range []string{frozen, open} {
		if err := os.WriteFile(path, []byte("<?php\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	both := stepFunc{"both", func(Pass) (Rewrites, error) {
		rewrites := rewriting(open, "changed")
		rewrites.Set(frozen, "changed")

		return rewrites, nil
	}}
	created := stepFunc{"created", func(Pass) (Rewrites, error) { return rewriting(filepath.Join(root, "New.vue"), "new"), nil }}

	converged := Converge((&Chain{}).Append(both).Append(created), []string{root}, everywhere{}, frozenSet{frozen: true})

	if got := converged.Files.Paths(); !slices.Equal(got, []string{filepath.Join(root, "New.vue")}) {
		t.Fatalf("got %v", got)
	}
}

func TestAChainThatNeverSettlesStopsAfterTheLastSweep(t *testing.T) {
	sweeps := 0
	grows := stepFunc{"grows", func(pass Pass) (Rewrites, error) {
		sweeps++

		return rewriting("a", pass.Drafts.Content("a")+"a"), nil
	}}

	converged := Converge((&Chain{}).Append(grows), nil, everywhere{}, frozenSet{})

	if converged.Settled || sweeps != MaxSweeps {
		t.Fatalf("settled %v after %d sweeps", converged.Settled, sweeps)
	}
}
