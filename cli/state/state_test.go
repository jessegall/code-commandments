package state

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"testing"
)

// The legends and states testdata/*.state were written from by the PHP StateFile.
var (
	counted = &Legend{
		About:     "How often the judge nudge has fired this session.",
		Variables: []Variable{{"count", "nudges so far"}, {"last_seen", "when the last one fired"}, {"muted", "yes when the user silenced it"}},
		Defaults:  New(Int("count", 0)),
	}
	listed = &Legend{
		About:     "The files this session touched.",
		Variables: []Variable{{"since", "when the list began"}},
		List:      "one touched path per line",
		Safe:      "the next edit starts it again",
	}
	bare = &Legend{About: "Only a list.", List: "names"}
)

func TestAFileIsWrittenByteForByteAsPhpWroteIt(t *testing.T) {
	for golden, write := range map[string]func(string) error{
		"counter.state": func(path string) error {
			return At(path, counted).Write(New(Text("last_seen", "line one\n\n  line two  "), Flag("muted", true)))
		},
		"touched.state": func(path string) error {
			return At(path, listed).Write(New(Text("since", "2026-09-25")).WithItems([]string{"src/A.php", "src/B.vue"}))
		},
		"bare.state": func(path string) error {
			return At(path, bare).Write(New().WithItems([]string{"x", "y"}))
		},
	} {
		path := filepath.Join(t.TempDir(), "sub", golden)

		if err := write(path); err != nil {
			t.Fatal(err)
		}

		want, _ := os.ReadFile(filepath.Join("testdata", golden))
		got, _ := os.ReadFile(path)

		if string(got) != string(want) {
			t.Errorf("%s\n--- want\n%s\n--- got\n%s", golden, want, got)
		}
	}
}

func TestAPhpWrittenFileReadsBackByName(t *testing.T) {
	counter := At("testdata/counter.state", counted).Read()
	touched := At("testdata/touched.state", listed).Read()

	switch {
	case counter.Int("count", 9) != 0 || !counter.Flag("muted") || counter.Text("last_seen", "") != "line one line two":
		t.Errorf("counter: %v", counter.Assignments(": "))
	case touched.Text("since", "") != "2026-09-25" || !slices.Equal(touched.Items(), []string{"src/A.php", "src/B.vue"}):
		t.Errorf("touched: %v %v", touched.Assignments(": "), touched.Items())
	}
}

func TestAMissingFileReadsAsTheEmptyState(t *testing.T) {
	missing := At(filepath.Join(t.TempDir(), "none.state"), counted).Read()

	if missing.Has("count") || missing.Int("count", 7) != 7 {
		t.Errorf("missing: %v", missing.Assignments(": "))
	}
}

func TestAValueNoLongerDeclaredIsDroppedOnRead(t *testing.T) {
	path := filepath.Join(t.TempDir(), "old.state")
	os.WriteFile(path, []byte("count: 3\nretired: x\n-----\nlegend\n"), 0o644)

	if got := At(path, counted).Read().Names(); !slices.Equal(got, []string{"count"}) {
		t.Errorf("names %v", got)
	}
}

func TestAnUndeclaredNamePanicsWhereItIsWrittenOrRead(t *testing.T) {
	for what, use := range map[string]func(){
		"write": func() { At(filepath.Join(t.TempDir(), "x.state"), counted).Write(New(Int("cuont", 1))) },
		"read":  func() { At("testdata/counter.state", counted).Read().Int("cuont", 0) },
		"with":  func() { At("testdata/counter.state", counted).Read().With(Int("cuont", 1)) },
	} {
		func() {
			defer func() {
				var unknown *UnknownValue

				if err, _ := recover().(error); !errors.As(err, &unknown) || unknown.Name != "cuont" {
					t.Errorf("%s: recovered %v", what, err)
				}
			}()

			use()
		}()
	}
}
