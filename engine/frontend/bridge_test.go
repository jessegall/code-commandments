package frontend

import (
	"bufio"
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

const fixture = "../../tests/Fixtures/frontend"

func bridged(t *testing.T, arguments ...string) *contract.Stream {
	t.Helper()
	if _, err := exec.LookPath("node"); err != nil {
		t.Skip("node is not on PATH")
	}
	stream, err := Here().Stream(arguments...)
	if err != nil {
		t.Fatal(err)
	}

	return stream
}

func TestTheFixtureStreamKeepsTheContract(t *testing.T) {
	stream := bridged(t, fixture)
	if stream.Header.Language != contract.Vue {
		t.Errorf("a stream holding .vue files is written as %q", stream.Header.Language)
	}
	if len(stream.Files) == 0 || stream.Trailer.Files != len(stream.Files) {
		t.Fatalf("the trailer counts %d files, the stream holds %d", stream.Trailer.Files, len(stream.Files))
	}
	for _, file := range stream.Files {
		source, err := os.ReadFile(file.Path)
		if err != nil {
			t.Fatalf("%s names no file: %v", file.Path, err)
		}
		if file.Errors != 0 {
			t.Errorf("%s: %d syntax errors in a fixture that parses", file.Path, file.Errors)
		}
		for _, node := range file.Nodes() {
			if node.Span.End > len(source) || node.Span.Start > node.Span.End {
				t.Fatalf("%s: node %d (%s) spans [%d, %d) of %d bytes", file.Path, node.ID, node.Kind, node.Span.Start, node.Span.End, len(source))
			}
		}
	}
}

func TestEachFileIsWrittenInItsOwnLanguage(t *testing.T) {
	for _, file := range bridged(t, fixture).Files {
		want := contract.TypeScript
		if strings.HasSuffix(file.Path, ".vue") {
			want = contract.Vue
		}
		if file.Language != want {
			t.Errorf("%s is written as %q", file.Path, file.Language)
		}
	}
}

func TestAFileOutsideWriteOnlyInforms(t *testing.T) {
	judged := filepath.Join(fixture, "client")
	for _, file := range bridged(t, "--write="+judged, fixture).Files {
		inside := strings.Contains(file.Path, "/client/")
		if file.Context == inside {
			t.Errorf("%s: context is %v", file.Path, file.Context)
		}
	}
}

// TestTheBridgeShipsVuesDeclarations checks that each Vue package typing a ref is beside the bundle and tracked by git,
// so a project with no vue installed, this checkout's own tests included, still reads a ref's value type.
func TestTheBridgeShipsVuesDeclarations(t *testing.T) {
	shipped := filepath.Join("..", "..", "bridge", "frontend", "dist", "types", "node_modules")
	for _, name := range []string{"vue", "@vue/runtime-dom", "@vue/runtime-core", "@vue/reactivity", "@vue/shared"} {
		manifest := filepath.Join(shipped, name, "package.json")
		text, err := os.ReadFile(manifest)
		if err != nil {
			t.Errorf("the bridge ships no %s; run npm run build in bridge/frontend: %v", name, err)
			continue
		}
		var declared struct {
			Types string `json:"types"`
		}
		if err := json.Unmarshal(text, &declared); err != nil {
			t.Fatalf("%s: %v", manifest, err)
		}
		if _, err := os.Stat(filepath.Join(shipped, name, declared.Types)); err != nil {
			t.Errorf("%s names %s as its types, and the bridge does not ship it", name, declared.Types)
		}
		if exec.Command("git", "check-ignore", "-q", "--no-index", manifest).Run() == nil {
			t.Errorf("git ignores the shipped %s, so a fresh checkout has none", name)
		}
	}
}

// TestARealAppKeepsTheContract runs the bridge over the app COMMANDMENTS_VUE_APP names, when it names one.
func TestARealAppKeepsTheContract(t *testing.T) {
	app := os.Getenv("COMMANDMENTS_VUE_APP")
	if app == "" {
		t.Skip("COMMANDMENTS_VUE_APP names no app")
	}
	stream := bridged(t, app)
	errors := 0
	for _, file := range stream.Files {
		errors += file.Errors
	}
	resolution := stream.Trailer.Resolution
	t.Logf("%d files, %d syntax errors, %d of %d expressions typed, %d of %d calls resolved",
		len(stream.Files), errors, *resolution.Typed, *resolution.Expressions, *resolution.Resolved, *resolution.Calls)
}

func TestServeAnswersEachRequestWithAWholeStream(t *testing.T) {
	if _, err := exec.LookPath("node"); err != nil {
		t.Skip("node is not on PATH")
	}
	bridge, err := Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	command := exec.Command(bridge[0], append(bridge[1:], "--serve")...)
	in, _ := command.StdinPipe()
	out, _ := command.StdoutPipe()
	if err := command.Start(); err != nil {
		t.Fatal(err)
	}
	defer command.Process.Kill()
	lines := bufio.NewScanner(out)
	lines.Buffer(nil, 64<<20)
	for _, folder := range []string{"client", "types"} {
		request, _ := json.Marshal(map[string][]string{"paths": {filepath.Join(fixture, folder)}})
		in.Write(append(request, '\n'))
		var stream strings.Builder
		for lines.Scan() {
			stream.WriteString(lines.Text() + "\n")
			if strings.HasPrefix(lines.Text(), `{"trailer"`) {
				break
			}
		}
		read, err := contract.ReadAll(strings.NewReader(stream.String()))
		if err != nil {
			t.Fatalf("the answer for %s breaks the contract: %v", folder, err)
		}
		if len(read.Files) == 0 || !strings.Contains(read.Files[0].Path, "/"+folder+"/") {
			t.Errorf("the answer for %s holds the wrong files", folder)
		}
	}
}

// An import reaches the component it names though that component lies outside the scan, as it reaches a TypeScript
// module there: the component is read from disk to be resolved, and never written.
func TestAnImportReachesAComponentOutsideTheScan(t *testing.T) {
	if _, err := exec.LookPath("node"); err != nil {
		t.Skip("node is not on PATH")
	}
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	files := map[string]string{
		"Pages/Show.vue":     "<script setup lang=\"ts\">\nimport Row from '../Components/Row.vue';\n</script>\n<template><Row /></template>\n",
		"Components/Row.vue": "<script setup lang=\"ts\">\ndefineProps<{ label: string }>();\n</script>\n<template><li>{{ label }}</li></template>\n",
	}
	for path, source := range files {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := Here().Scan(filepath.Join(root, "Pages"))
	if err != nil {
		t.Fatal(err)
	}
	if len(codebase.Files()) != 1 {
		t.Fatalf("the scan wrote %d files", len(codebase.Files()))
	}
	want := filepath.Join(root, "Components", "Row.vue")
	resolved := map[string]bool{}
	for _, file := range codebase.Files() {
		for _, node := range file.Match(file.Root.ID).Descendants() {
			if node.Resolves() != "" {
				resolved[node.Kind()+" "+node.Resolves()] = true
			}
		}
	}
	if !resolved["ImportDeclaration "+want] || !resolved["Element "+want] {
		t.Errorf("the import and the tag do not reach %s: %v", want, resolved)
	}
}
