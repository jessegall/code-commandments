package bridge

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestTheRoslynBridgeWritesAValidTreeOfTheCSharpFixture(t *testing.T) {
	root, err := filepath.Abs("../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := Once(TestRoslyn(t), root)
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
	server, err := Serve(TestRoslyn(t))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	written := filepath.Join(root, "Shop/Orders/GiftWrapping.cs")
	stream, err := server.Ask(Request{Paths: []string{root}, Write: []string{written}})
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
	stream, err := Once(TestRoslyn(t), root)
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
	if _, err := Once(TestRoslyn(t), root); err != nil {
		t.Fatal(err)
	}
}
