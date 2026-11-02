package scan

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/php"
)

// A bridge that dies takes the files of its run with it, and on one reader's machine it died on two whole folders
// every time, so no C# of that project was ever judged (#765). Nothing here can repair a fault in the runtime it
// dies in; what it can do is make the death cost the files that cause it rather than the language. These hold the
// halving: the set is cut in two until the dying part is small, every other file is read, and the cutting has a
// floor so one death never becomes hundreds of bridge runs.

// deadly is a bridge reader that dies on any request holding the named file and answers the rest, counting its
// runs. The written stream it answers with holds one file, as a real one would hold what it read.
func deadly(t *testing.T, kills string) (*Bridge, *[]([]string)) {
	t.Helper()
	root := t.TempDir()
	path := filepath.Join(root, "Only.php")
	if err := os.WriteFile(path, []byte("<?php\nfinal class Only {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	written, err := php.Here().Stream(path)
	if err != nil {
		t.Fatal(err)
	}

	var asked [][]string
	held := Bridge{Kind: "php", Word: "php", Label: "PHP"}
	route := read
	read = func(_ Bridge, _ []string, files []string, _ func()) (*contract.Stream, error) {
		asked = append(asked, files)
		if slices.ContainsFunc(files, func(file string) bool { return strings.HasSuffix(file, kills) }) {
			return nil, errors.New("signal: killed")
		}

		one := *written
		return &one, nil
	}
	t.Cleanup(func() { read = route })

	return &held, &asked
}

// TestADeadBridgeCostsTheFilesThatKillItAndNoMore holds the whole point: one file of eight kills the bridge, and
// the halving reads the other seven.
func TestADeadBridgeCostsTheFilesThatKillItAndNoMore(t *testing.T) {
	held, asked := deadly(t, "Deadly.php")

	var files []string
	for _, name := range []string{"A", "B", "C", "D", "Deadly", "F", "G", "H"} {
		files = append(files, filepath.Join("src", name+".php"))
	}

	stream, err := held.readHalving([]string{"src"}, files, func() {}, halvingsAtMost)

	if err == nil {
		t.Error("the run says nothing of the file it could not read")
	}
	if stream == nil || len(stream.Files) == 0 {
		t.Fatalf("every file was given up with the one that killed the bridge: %v", stream)
	}

	// The halves that answered are what the stream holds, and the one file nobody could read is not among them.
	for _, read := range stream.Files {
		if strings.HasSuffix(read.Path, "Deadly.php") {
			t.Errorf("the file that killed the bridge reads as read: %s", read.Path)
		}
	}

	// The dying half is cut until it is one file, and every ask names the files of that part alone.
	smallest := len(files)
	for _, set := range *asked {
		smallest = min(smallest, len(set))
	}
	if smallest != 1 {
		t.Errorf("the dying set was cut down to %d files, and the file that kills it is one", smallest)
	}
}

// TestTheCuttingHasAFloor holds what keeps a death from becoming hundreds of bridge runs: a bridge that dies
// whatever it is asked has not died on the set, so the halving stops rather than cutting to every single file.
func TestTheCuttingHasAFloor(t *testing.T) {
	var asked [][]string
	route := read
	read = func(_ Bridge, _ []string, files []string, _ func()) (*contract.Stream, error) {
		asked = append(asked, files)

		return nil, errors.New("signal: killed")
	}
	t.Cleanup(func() { read = route })

	held := Bridge{Kind: "php", Word: "php", Label: "PHP"}
	var files []string
	for at := range 64 {
		files = append(files, filepath.Join("src", string(rune('a'+at%26))+string(rune('a'+at/26))+".php"))
	}

	if _, err := held.readHalving([]string{"src"}, files, func() {}, 3); err == nil {
		t.Error("a bridge that dies whatever it is asked is reported as having answered")
	}

	// Three halvings over a set that always dies: the whole set, then two halves, then four, then eight.
	if len(asked) > 15 {
		t.Errorf("a bridge that dies whatever it is asked was run %d times", len(asked))
	}
}

// TestWhatIsHalvedIsWhatTheBridgeWritesAndNotWhatItCompiles holds the one thing the halving must never do. A C#
// bridge compiles whole roots and writes the files it is asked for, so each half is read against the WHOLE project
// and a type that crosses the halves goes on resolving. Halving the compilation would answer too, and answer
// differently, which is worse than a file left unread because nothing would say so.
func TestWhatIsHalvedIsWhatTheBridgeWritesAndNotWhatItCompiles(t *testing.T) {
	held, _ := deadly(t, "Deadly.cs")
	held.WholeRoots = true

	var compiled [][]string
	route := read
	read = func(b Bridge, roots []string, files []string, _ func()) (*contract.Stream, error) {
		request := b.Request(roots, files)
		compiled = append(compiled, request.Paths)
		if slices.ContainsFunc(files, func(file string) bool { return strings.HasSuffix(file, "Deadly.cs") }) {
			return nil, errors.New("signal: killed")
		}

		return &contract.Stream{Header: contract.Header{Language: "csharp"}}, nil
	}
	t.Cleanup(func() { read = route })

	roots := []string{"src"}
	held.readHalving(roots, []string{"src/A.cs", "src/Deadly.cs"}, func() {}, halvingsAtMost)

	for _, paths := range compiled {
		if !slices.Equal(paths, roots) {
			t.Errorf("a half was compiled over %v rather than over the whole project %v", paths, roots)
		}
	}
}
