package bridge_test

import (
	"crypto/sha1"
	"encoding/hex"
	"fmt"
	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

func TestTheRoslynBridgeWritesAValidTreeOfTheCSharpFixture(t *testing.T) {
	root, err := filepath.Abs("../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridgetest.Roslyn(t, root), root)
	if err != nil {
		t.Fatal(err)
	}
	if stream.Header.Language != contract.CSharp {
		t.Errorf("the stream is %s, not csharp", stream.Header.Language)
	}
	if len(stream.Files) == 0 {
		t.Error("the stream holds no file")
	}
	refs, forgiven := 0, 0
	for _, file := range stream.Files {
		inSourceOrder(t, file)
		for _, comment := range file.Comments {
			refs += len(comment.Refs)
		}
		for _, node := range file.Nodes() {
			if node.Extras != nil && node.Extras.CSharp != nil && node.Extras.CSharp.ForgivesNull {
				forgiven++
			}
		}
	}
	if refs == 0 || forgiven == 0 {
		t.Errorf("the fixture's doc references (%d) and forgiven nulls (%d) are not all written", refs, forgiven)
	}
}

func TestAServedRoslynBridgeMarksTheFilesItWasNotAskedToWriteAsContext(t *testing.T) {
	root, err := filepath.Abs("../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	server, err := bridge.Serve(bridgetest.Roslyn(t, root))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	written := filepath.Join(root, "Shop/Orders/GiftWrapping.cs")
	stream, err := server.Ask(bridge.Request{Paths: []string{root}, Write: []string{written}})
	if err != nil {
		t.Fatal(err)
	}
	judged := 0
	for _, file := range stream.Files {
		if !file.Context {
			judged++
		}
	}
	if judged != 1 || len(stream.Files) < 2 {
		t.Errorf("%d of %d files are judged; only the one asked for should be", judged, len(stream.Files))
	}
}

func TestTheRoslynBridgeWritesAnExpressionNestedDeeperThanTheSerializersDefault(t *testing.T) {
	root := t.TempDir()
	source := "namespace Shop;\n\npublic static class Banner\n{\n    public static string Text() => \"a\"" + strings.Repeat(" + \"a\"", 200) + ";\n}\n"
	if err := os.WriteFile(filepath.Join(root, "Banner.cs"), []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridgetest.Roslyn(t, root), root)
	if err != nil {
		t.Fatal(err)
	}
	if len(stream.Files) != 1 {
		t.Errorf("the stream holds %d files, not the banner", len(stream.Files))
	}
}

func TestTheRoslynBridgeWritesATypeItCouldNotResolveInsideAnotherAsOpaque(t *testing.T) {
	root := t.TempDir()
	source := "namespace Shop;\n\npublic sealed class Shelf\n{\n    public (int Count, Missing Item) Top() => default;\n\n    public System.Collections.Generic.List<Missing> All() => new();\n\n    public void Report() => Print((\"shelves\", missing.Count.ToString()));\n\n    private void Print((string, string) row) { }\n}\n"
	if err := os.WriteFile(filepath.Join(root, "Shelf.cs"), []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}
	if _, err := bridge.Once(bridgetest.Roslyn(t, root), root); err != nil {
		t.Fatal(err)
	}
}

func TestTheImageNameIsTheOneTheBridgesSourcesAreBuiltAs(t *testing.T) {
	files, err := filepath.Glob("roslyn/*.cs")
	if err != nil {
		t.Fatal(err)
	}
	files = append(files, "roslyn/Dockerfile", "roslyn/Roslyn.Bridge.csproj")
	for at := range files {
		files[at] = filepath.Base(files[at])
	}
	sort.Strings(files)
	hash := sha1.New()
	for _, file := range files {
		source, err := os.ReadFile(filepath.Join("roslyn", file))
		if err != nil {
			t.Fatal(err)
		}
		hash.Write([]byte(file + "\n"))
		hash.Write(source)
	}
	if want := "ghcr.io/jessegall/code-commandments-roslyn:" + hex.EncodeToString(hash.Sum(nil))[:16]; bridge.RoslynImage() != want {
		t.Errorf("bridge/roslyn/IMAGE names %s, but the sources are built as %s: write the new name there", bridge.RoslynImage(), want)
	}
}

func TestTheRoslynBridgeGivenAPathItCannotSeeSaysSoAndExitsWithinSeconds(t *testing.T) {
	root := t.TempDir()
	command := bridgetest.Roslyn(t, root)
	gone := filepath.Join(root, "gone")
	start := time.Now()
	errs, ran, _ := bounded(t, time.Minute, func() (string, bool) {
		_, errs, ran, _ := bridge.Run(command, gone)

		return errs, ran
	})
	if ran {
		t.Fatal("the bridge exited cleanly over a path that is not there")
	}
	if want := "roslyn-bridge: there is no file or folder at " + gone + " to read"; strings.TrimSpace(errs) != want {
		t.Errorf("the bridge said %q, not the one line %q", errs, want)
	}
	if took := time.Since(start); took > 30*time.Second {
		t.Errorf("the bridge took %s to fail", took)
	}
}

func TestAServedRoslynBridgeAskedForAPathItCannotSeeSaysSoAndExitsWithinSeconds(t *testing.T) {
	root := t.TempDir()
	server, err := bridge.Serve(bridgetest.Roslyn(t, root))
	if err != nil {
		t.Fatal(err)
	}
	gone := filepath.Join(root, "gone")
	start := time.Now()
	asked, closed, _ := bounded(t, time.Minute, func() (string, bool) {
		_, asked := server.Ask(bridge.Request{Paths: []string{gone}})
		closed := server.Close()

		return fmt.Sprint(asked), closed == nil
	})
	if !strings.Contains(asked, "roslyn-bridge: there is no file or folder at "+gone+" to read") {
		t.Errorf("the served bridge answered %q, not why it failed", asked)
	}
	if closed {
		t.Error("the served bridge exited cleanly after a request it could not read")
	}
	if took := time.Since(start); took > 30*time.Second {
		t.Errorf("the served bridge took %s to fail", took)
	}
}

// bounded runs the call, failing the test rather than waiting past the limit for it.
func bounded(t *testing.T, limit time.Duration, call func() (string, bool)) (string, bool, bool) {
	t.Helper()
	type answer struct {
		text string
		ok   bool
	}
	answered := make(chan answer, 1)
	go func() {
		text, ok := call()
		answered <- answer{text, ok}
	}()
	select {
	case got := <-answered:
		return got.text, got.ok, true
	case <-time.After(limit):
		t.Fatalf("the bridge was still running after %s", limit)
	}

	return "", false, false
}
