package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// TestEveryFileWaitingGoesOverInOneAsk holds what the whole saving rests on. A bridge's cost is per ASK and not
// per file — measured here, one ask of nine files costs 349ms against 343ms for one — so the files of the edits
// waiting together must go over in ONE ask. Asked one at a time they cost that whole price each: nine edits took
// 4.025 seconds to clear and 430ms of judging apiece, against 2.838 and 118 once they shared an ask.
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

	// Started once all three are waiting, which is what an ask that takes 350ms means in a session: the files of
	// every event that arrived while the last ask ran are waiting together when the next one is made.
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

	// And each of them is then held, so the judging of that edit parses nothing at all.
	for _, file := range files {
		if _, already := parser.streamsOf(file); !already {
			t.Errorf("%s was parsed ahead and is not held, so its judging parses it again", filepath.Base(file))
		}
	}
}

// TestAParseIsNotUsedForAFileThatMovedAgain holds the one thing a parse held ahead must never do: answer about a
// file as it WAS. An edit that lands while its own file is being parsed is a file that moved again, and the
// judging must parse it itself rather than read a tree of the version before.
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

	// The next edit lands: same path, different content, and a mark that says so.
	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(file, []byte("x = 1\ny = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if _, already := parser.streamsOf(file); already {
		t.Error("a parse of the file as it was is being handed to the judging of the file as it is")
	}
}

// parsingFor is a parser whose asks this test can count. Its worker is NOT started: a test says when, so it can
// have several files waiting before the first ask, as a session does.
func parsingFor(t *testing.T, asked chan []string, hold chan struct{}) *parsing {
	t.Helper()

	parser := &parsing{
		held:   map[string]read{},
		asking: map[string]bool{},
		filled: make(chan struct{}),
		wake:    make(chan struct{}, 1),
		ahead:   true,
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

// parsedFiles are the files a bridge would have written for that ask.
func parsedFiles(files []string) []*contract.File {
	var written []*contract.File
	for _, file := range files {
		written = append(written, &contract.File{Path: file, Language: contract.Python})
	}

	return written
}

// TestAJudgingWaitsForAnAskAlreadyInFlight holds the one thing that made this cost more than it saved. A judging
// whose turn comes while its own file is mid-ask must WAIT for that ask: parsing the file itself instead makes a
// second ask to the same one-at-a-time bridge, which queues behind the first and is then thrown away, so the file
// is parsed twice and the edit pays for both. Measured on a real tree as an edit spending 30 seconds reading
// while three hand-ins ran.
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
