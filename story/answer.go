package story

import (
	"fmt"
	"strings"
)

func (s *Story) Says(ran Ran, texts ...string) {
	s.t.Helper()

	for _, text := range texts {
		if !strings.Contains(ran.Text(), text) {
			s.Fails(fmt.Sprintf("the answer does not say %q; it says:\n%s", text, ran.Text()))
		}
	}
}

func (s *Story) SaysOnce(ran Ran, text string) {
	s.t.Helper()

	if found := strings.Count(ran.Text(), text); found != 1 {
		s.Fails(fmt.Sprintf("the answer says %q %d times, where it says it once; it says:\n%s", text, found, ran.Text()))
	}
}

func (s *Story) Never(ran Ran, texts ...string) {
	s.t.Helper()

	for _, text := range texts {
		if strings.Contains(ran.Text(), text) {
			s.Fails(fmt.Sprintf("the answer says %q, which it must not; it says:\n%s", text, ran.Text()))
		}
	}
}

func (s *Story) Names(ran Ran, file, sin string) {
	s.t.Helper()

	s.Says(ran, file, sin)
}

func (s *Story) Exits(ran Ran, code int) {
	s.t.Helper()

	if ran.Code != code {
		s.Fails(fmt.Sprintf("the command exited with %d, where %d was meant; it says:\n%s", ran.Code, code, ran.Text()))
	}
}

func (s *Story) Refuses(ran Ran) {
	s.t.Helper()

	if ran.Code == 0 {
		s.Fails("the command was meant to be refused and exited with 0; it says:\n" + ran.Text())
	}
}

func (s *Story) CostIs(name string, counted int) {
	s.t.Helper()

	if got := s.Cost(name); got != counted {
		s.Fails(fmt.Sprintf("%s counted %d since the story began, where %d was meant", name, got, counted))
	}
}

func (s *Story) CostAtMost(name string, most int) {
	s.t.Helper()

	if got := s.Cost(name); got > most {
		s.Fails(fmt.Sprintf("%s counted %d since the story began, past the %d it may cost", name, got, most))
	}
}

func (s *Story) CostAtLeast(name string, least int) {
	s.t.Helper()

	if got := s.Cost(name); got < least {
		s.Fails(fmt.Sprintf("%s counted %d since the story began, short of the %d it must cost", name, got, least))
	}
}

func (s *Story) DropsTheLane(checkout string) Ran {
	s.t.Helper()

	return s.Asks("commandments", "lanes", "--drop", checkout)
}

func (s *Story) ReadsTheLanes() Ran {
	s.t.Helper()

	return s.Asks("commandments", "lanes")
}
