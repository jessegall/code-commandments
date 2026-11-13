package scan

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/php"
)

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
	read = func(_ Bridge, _ *keptLines, _, _, files []string, _ func()) (*contract.Stream, error) {
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

func TestADeadBridgeCostsTheFilesThatKillItAndNoMore(t *testing.T) {
	held, asked := deadly(t, "Deadly.php")

	var files []string
	for _, name := range []string{"A", "B", "C", "D", "Deadly", "F", "G", "H"} {
		files = append(files, filepath.Join("src", name+".php"))
	}

	stream, err := held.readHalving(&keptLines{}, []string{"src"}, files, files, func() {}, halvingsAtMost)

	if err == nil {
		t.Error("the run says nothing of the file it could not read")
	}
	if stream == nil || len(stream.Files) == 0 {
		t.Fatalf("every file was given up with the one that killed the bridge: %v", stream)
	}

	for _, read := range stream.Files {
		if strings.HasSuffix(read.Path, "Deadly.php") {
			t.Errorf("the file that killed the bridge reads as read: %s", read.Path)
		}
	}

	smallest := len(files)
	for _, set := range *asked {
		smallest = min(smallest, len(set))
	}
	if smallest != 1 {
		t.Errorf("the dying set was cut down to %d files, and the file that kills it is one", smallest)
	}
}

func TestTheCuttingHasAFloor(t *testing.T) {
	var asked [][]string
	route := read
	read = func(_ Bridge, _ *keptLines, _, _, files []string, _ func()) (*contract.Stream, error) {
		asked = append(asked, files)

		return nil, errors.New("signal: killed")
	}
	t.Cleanup(func() { read = route })

	held := Bridge{Kind: "php", Word: "php", Label: "PHP"}
	var files []string
	for at := range 64 {
		files = append(files, filepath.Join("src", string(rune('a'+at%26))+string(rune('a'+at/26))+".php"))
	}

	if _, err := held.readHalving(&keptLines{}, []string{"src"}, files, files, func() {}, 3); err == nil {
		t.Error("a bridge that dies whatever it is asked is reported as having answered")
	}

	if len(asked) > 15 {
		t.Errorf("a bridge that dies whatever it is asked was run %d times", len(asked))
	}
}

func TestWhatIsHalvedIsWhatTheBridgeWritesAndNotWhatItCompiles(t *testing.T) {
	held, _ := deadly(t, "Deadly.cs")
	held.Projects = bridge.CSharpProjects

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	project := filepath.Join(root, "App.csproj")
	files := []string{filepath.Join(root, "A.cs"), filepath.Join(root, "Deadly.cs")}
	for _, path := range append([]string{project}, files...) {
		if err := os.WriteFile(path, []byte("<Project />\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	var compiled [][]string
	route := read
	read = func(b Bridge, kept *keptLines, roots, _, files []string, _ func()) (*contract.Stream, error) {
		compiled = append(compiled, b.Request(files).Support)
		if slices.ContainsFunc(files, func(file string) bool { return strings.HasSuffix(file, "Deadly.cs") }) {
			return nil, errors.New("signal: killed")
		}

		return &contract.Stream{Header: contract.Header{Language: "csharp"}}, nil
	}
	t.Cleanup(func() { read = route })

	held.readHalving(&keptLines{}, []string{root}, files, files, func() {}, halvingsAtMost)

	for _, support := range compiled {
		if !slices.Contains(support, project) || !slices.Contains(support, files[0]) || !slices.Contains(support, files[1]) {
			t.Errorf("a half was compiled beside %v rather than beside the whole project", support)
		}
	}
}

func TestABridgeThatCannotRunIsAskedOnceAndNeverHalved(t *testing.T) {
	var asked int
	route := read
	read = func(Bridge, *keptLines, []string, []string, []string, func()) (*contract.Stream, error) {
		asked++

		return nil, bridge.ToolchainMissing{Language: "Python", Need: "python3.10 or newer on the PATH"}
	}
	t.Cleanup(func() { read = route })

	held := Bridge{Kind: "mypy", Word: "python", Label: "Python"}
	var files []string
	for at := range 64 {
		files = append(files, filepath.Join("src", string(rune('a'+at%26))+string(rune('a'+at/26))+".py"))
	}

	if _, err := held.readHalving(&keptLines{}, []string{"src"}, files, files, func() {}, halvingsAtMost); !isUnavailable(err) {
		t.Errorf("a bridge with no toolchain was reported as %v", err)
	}

	if asked != 1 {
		t.Errorf("a bridge with no toolchain was started %d times; it can never run, so once is enough", asked)
	}
}
