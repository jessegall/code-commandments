package scribes

import (
	"slices"
	"testing"
)

type named string

func (n named) Name() string                 { return string(n) }
func (n named) Run(Pass) (Rewrites, error)   { return Rewrites{}, nil }
func (n named) MatchesSin(query string) bool { return query == "sin-of-"+string(n) }

func namesOf(chain *Chain) []string {
	var names []string
	for _, step := range chain.Steps() {
		names = append(names, step.Name())
	}

	return names
}

func TestPrependAppendBeforeAfterReplaceRemove(t *testing.T) {
	chain := (&Chain{}).Append(named("a")).Append(named("b")).Append(named("c"))
	chain.Prepend(named("first")).
		Append(named("last")).
		Before("b", named("pre-b")).
		After("b", named("post-b")).
		Replace("c", named("c2")).
		Remove("a")

	want := []string{"first", "pre-b", "b", "post-b", "c2", "last"}
	if got := namesOf(chain); !slices.Equal(got, want) {
		t.Fatalf("got %v, want %v", got, want)
	}
}

func TestBeforeAndAfterAppendWhenTheNameIsMissing(t *testing.T) {
	chain := (&Chain{}).Append(named("a"))
	chain.Before("nope", named("x")).After("nope", named("y"))

	want := []string{"a", "x", "y"}
	if got := namesOf(chain); !slices.Equal(got, want) {
		t.Fatalf("got %v, want %v", got, want)
	}
}

func TestMatchingNarrowsToAScope(t *testing.T) {
	chain := (&Chain{}).Append(named("AlphaDetector")).Append(named("BetaDetector")).Append(named("Gamma")).Matching("beta")
	if got := namesOf(chain); !slices.Equal(got, []string{"BetaDetector"}) {
		t.Fatalf("got %v", got)
	}

	bySin := (&Chain{}).Append(named("Alpha")).Append(named("Gamma")).Matching("sin-of-Gamma")
	if got := namesOf(bySin); !slices.Equal(got, []string{"Gamma"}) {
		t.Fatalf("got %v", got)
	}
}
