package story

import (
	"path/filepath"
	"regexp"
	"strings"
)

const scratchPrefix = "story_scratch_"

var notALetter = regexp.MustCompile(`[^a-z0-9]+`)

// Sample is a piece of source a story writes into a checkout: the folder of the checkout it is judged under, a label that says what it holds, and its text. The label is part of the step it takes, so two stories that write different things are different stories.
type Sample struct {
	Under string
	Label string
	Text  string
}

func Python(label, text string) Sample {
	return Sample{Under: "bridge/mypy", Label: label, Text: text}
}

func PHP(label, text string) Sample {
	return Sample{Under: "bridge/php/src", Label: label, Text: text}
}

func CSharp(label, text string) Sample {
	return Sample{Under: "bridge/roslyn", Label: label, Text: text}
}

func (s *Story) Writes(sample Sample, file string) string {
	s.t.Helper()

	return s.WritesIn(s.TestCheckout(), sample, file)
}

func (s *Story) WritesIn(checkout string, sample Sample, file string) string {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("writes "+shapeOf(sample.Label)+" as %s", s.own(file)))

	path := s.writesQuietly(checkout, sample, file)

	if ran, err := s.journal.LooksAt(checkout); !s.rehearsal && (err != nil || ran.Code != 0) {
		s.Fails("the journal could not look at " + checkout + ": " + failure(ran, err))
	}

	return path
}

func (s *Story) writesQuietly(checkout string, sample Sample, file string) string {
	s.t.Helper()

	folder := s.ScratchIn(checkout, sample.Under)
	path := filepath.Join(folder, file)

	s.removesAtTheEnd(folder)

	if ran, err := s.journal.Writes(path, sample.Text); err != nil || ran.Code != 0 {
		s.Fails("the file " + path + " could not be written: " + failure(ran, err))
	}

	return path
}

func (s *Story) Removes(sample Sample, file string) {
	s.t.Helper()

	path := filepath.Join(s.ScratchIn(s.TestCheckout(), sample.Under), file)

	s.steps = append(s.steps, StepOf("removes "+shapeOf(sample.Label)+" as %s", s.own(file)))

	if ran, err := s.journal.Removes(path); err != nil || ran.Code != 0 {
		s.Fails("the file " + path + " could not be removed: " + failure(ran, err))
	}
}

func (s *Story) Scratch(under string) string {
	return s.ScratchIn(s.TestCheckout(), under)
}

func (s *Story) ScratchIn(checkout, under string) string {
	return filepath.Join(checkout, under, scratchPrefix+notALetter.ReplaceAllString(strings.ToLower(s.t.Name()), "_"))
}

func (s *Story) own(file string) string {
	return filepath.Join(filepath.Base(s.ScratchIn("", "")), file)
}

func (s *Story) removesAtTheEnd(folder string) {
	if s.scratches[folder] {
		return
	}

	if s.scratches == nil {
		s.scratches = map[string]bool{}
	}

	s.scratches[folder] = true

	s.t.Cleanup(func() { _, _ = s.journal.Removes(folder) })
}

func shapeOf(label string) string {
	return strings.ReplaceAll(label, "%", "%%")
}

func failure(ran Ran, err error) string {
	if err != nil {
		return err.Error()
	}

	return ran.Err
}
