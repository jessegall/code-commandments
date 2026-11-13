package cache_test

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cache"
)

func TestAnUnusedCacheIsKeptTwelveHoursUnlessTheProjectSaysOtherwise(t *testing.T) {
	for said, want := range map[string]time.Duration{
		"":       cache.Default,
		"3":      3 * time.Hour,
		"0.5":    30 * time.Minute,
		"0":      cache.Default,
		"-4":     cache.Default,
		"twelve": cache.Default,
	} {
		t.Setenv(cache.Variable, said)

		if kept := cache.KeptFor(); kept != want {
			t.Errorf("%q keeps an unused cache %v, want %v", said, kept, want)
		}
	}
}

func TestTheHoursAProjectChoseReachEveryoneTheRunStarts(t *testing.T) {
	t.Setenv(cache.Variable, "")

	cache.KeepFor(2)

	if kept := cache.KeptFor(); kept != 2*time.Hour {
		t.Errorf("after the project chose 2 hours the run keeps an unused cache %v", kept)
	}

	cache.KeepFor(0)

	if kept := cache.KeptFor(); kept != 2*time.Hour {
		t.Errorf("a project that chose nothing undid the hours already chosen: %v", kept)
	}
}
