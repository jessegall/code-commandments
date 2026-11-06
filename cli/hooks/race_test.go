//go:build race

package hooks

import (
	"flag"
	"testing"
)

func TestManyTreesAtOnceUnderTheRaceDetector(t *testing.T) {
	for named, given := range map[string]string{
		"stress":           "true",
		"stress.many":      "true",
		"stress.files":     "40",
		"stress.worktrees": "3",
		"stress.moves":     "4",
	} {
		if err := flag.Set(named, given); err != nil {
			t.Fatalf("the race run could not set -%s: %v", named, err)
		}
	}

	TestManyTreesAtOnce(t)
}
