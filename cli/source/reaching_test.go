package source

import (
	"slices"
	"testing"
)

// Every language is skipped the same way: a judging of one language's changed file reads that language and those it
// reads alongside, and no other.
func TestAJudgingReadsOnlyTheLanguagesItsChangedFilesReach(t *testing.T) {
	for _, language := range Languages {
		changed := []string{"/project/src/changed." + language.Extensions()[0]}
		reached := Reaching(changed, Languages)
		for _, other := range Languages {
			want := other == language || slices.Contains(language.ReadAlongside(), other)
			if slices.Contains(reached, other) != want {
				t.Errorf("a changed %s file: %s read is %v, want %v", language.Label(), other.Label(), !want, want)
			}
		}
	}
	if reached := Reaching(nil, Languages); !slices.Equal(reached, Languages) {
		t.Errorf("a judging naming no changed file read %v, not every language", reached)
	}
}
