package story

import (
	"fmt"
	"sort"
	"strings"
	"sync"
)

// Told is one story as the run told it: its name, the file it is written in and every step it took.
type Told struct {
	Name  string
	File  string
	Steps []Step
}

// Telling is every story told in one run, kept so the run can say which of them overlap.
type Telling struct {
	mutex sync.Mutex
	told  []Told
}

const sharedStepsThatMakeAFixture = 2

var thisRun = &Telling{}

func (t *Telling) Keeps(told Told) {
	t.mutex.Lock()
	defer t.mutex.Unlock()

	t.told = append(t.told, told)
}

func (t *Telling) Overlaps() []string {
	t.mutex.Lock()
	defer t.mutex.Unlock()

	told := append([]Told(nil), t.told...)
	sort.Slice(told, func(a, b int) bool { return told[a].Name < told[b].Name })

	var said []string

	for at, first := range told {
		for _, second := range told[at+1:] {
			said = append(said, overlapOf(first, second)...)
		}
	}

	return said
}

func overlapOf(first, second Told) []string {
	if len(first.Steps) > 0 && shapesOf(first.Steps) == shapesOf(second.Steps) {
		return []string{fmt.Sprintf("%s and %s take the same steps with other words, so they are one story told "+
			"twice: keep one, or make them differ in what they do", first.Name, second.Name)}
	}

	if first.File != second.File {
		return nil
	}

	if shared := sharedOpening(first.Steps, second.Steps); shared >= sharedStepsThatMakeAFixture {
		return []string{fmt.Sprintf("%s and %s open with the same %d steps, so those steps belong in a fixture both "+
			"start from:\n  %s", first.Name, second.Name, shared, strings.Join(textsOf(first.Steps[:shared]), "\n  "))}
	}

	return nil
}

func sharedOpening(first, second []Step) int {
	shared := 0
	for shared < len(first) && shared < len(second) && first[shared].Text() == second[shared].Text() {
		shared++
	}

	return shared
}

func textsOf(steps []Step) []string {
	texts := make([]string, len(steps))
	for at, step := range steps {
		texts[at] = step.Text()
	}

	return texts
}
