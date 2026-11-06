package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

func TestEveryFileWaitingGoesOverInOneAsk(t *testing.T) {
	asked := make(chan []string, 8)
	parser := parsingFor(t, asked, nil)

	folder := t.TempDir()

	var files []string
	for _, named := range []string{"one.py", "two.py", "three.py"} {
		file := filepath.Join(folder, named)
		if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}

		files = append(files, file)
		parser.expect(file)
	}

	go parser.work()

	var groups [][]string
	for parsed := 0; parsed < len(files); {
		select {
		case group := <-asked:
			groups = append(groups, group)
			parsed += len(group)
		case <-time.After(10 * time.Second):
			t.Fatalf("only %d of %d files were parsed", parsed, len(files))
		}
	}

	if len(groups) != 1 {
		t.Errorf("three files waiting went over in %d asks, want 1: %v", len(groups), groups)
	}

	for _, file := range files {
		if _, already := parser.streamsOf(file); !already {
			t.Errorf("%s was parsed ahead and is not held, so its judging parses it again", filepath.Base(file))
		}
	}
}

func TestAParseIsNotUsedForAFileThatMovedAgain(t *testing.T) {
	asked := make(chan []string, 8)
	parser := parsingFor(t, asked, nil)

	file := filepath.Join(t.TempDir(), "order.py")
	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	go parser.work()
	parser.expect(file)

	select {
	case <-asked:
	case <-time.After(10 * time.Second):
		t.Fatal("the file was never parsed")
	}

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(file, []byte("x = 1\ny = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if _, already := parser.streamsOf(file); already {
		t.Error("a parse of the file as it was is being handed to the judging of the file as it is")
	}
}

func parsingFor(t *testing.T, asked chan []string, hold chan struct{}) *parsing {
	t.Helper()

	parser := &parsing{
		held:   map[string]read{},
		asking: map[string]bool{},
		filled: make(chan struct{}),
		wake:   make(chan struct{}, 1),
		ahead:  true,
		reading: func(files []string) ([]*contract.Stream, error) {
			asked <- files

			if hold != nil {
				<-hold
			}

			return []*contract.Stream{{
				Header: contract.Header{Language: contract.Python},
				Files:  parsedFiles(files),
			}}, nil
		},
	}

	return parser
}

func parsedFiles(files []string) []*contract.File {
	var written []*contract.File
	for _, file := range files {
		written = append(written, &contract.File{Path: file, Language: contract.Python})
	}

	return written
}

func TestAJudgingWaitsForAnAskAlreadyInFlight(t *testing.T) {
	asked := make(chan []string, 8)
	hold := make(chan struct{})
	parser := parsingFor(t, asked, hold)

	file := filepath.Join(t.TempDir(), "order.py")
	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	go parser.work()
	parser.expect(file)

	select {
	case <-asked:
	case <-time.After(10 * time.Second):
		t.Fatal("the file was never asked about")
	}

	answered := make(chan bool, 1)
	go func() {
		_, already := parser.streamsOf(file)
		answered <- already
	}()

	select {
	case <-answered:
		t.Fatal("the judging answered while the ask for its own file was still in flight, so it parsed it again")
	case <-time.After(100 * time.Millisecond):
	}

	close(hold)

	select {
	case already := <-answered:
		if !already {
			t.Error("the judging waited for the ask and was not given its parse")
		}
	case <-time.After(10 * time.Second):
		t.Fatal("the judging never stopped waiting")
	}

	if waiting := len(asked); waiting != 0 {
		t.Errorf("%d further ask(s) were made for a file already being asked about", waiting)
	}
}

func TestAHalfWrittenFileDoesNotLoseTheGroupsParse(t *testing.T) {
	folder := t.TempDir()

	whole := filepath.Join(folder, "order.py")
	if err := os.WriteFile(whole, []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	half := filepath.Join(folder, "invoice.py")
	if err := os.WriteFile(half, []byte("class Invoice:\n    def total(:\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	streams, err := readTogether([]string{whole, half})
	if err != nil {
		t.Fatalf("an ask carrying one half-written file answered with nothing at all: %v", err)
	}

	read := map[string]bool{}
	for _, stream := range streams {
		for _, file := range stream.Files {
			read[file.Path] = true
		}
	}

	if !read[whole] {
		t.Errorf("the whole file %s was not read, because the file beside it was half written", whole)
	}
}
