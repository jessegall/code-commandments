package php_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

func TestAServedBridgeReadsDraftedTextInPlaceOfTheDisk(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	existing := filepath.Join(root, "A.php")
	if err := os.WriteFile(existing, []byte("<?php\nclass A {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	created := filepath.Join(root, "sub", "B.php")
	contents := map[string]string{
		existing: "<?php\n\nfinal class A {}\n",
		created:  "<?php\nclass B {}\n",
	}

	command, err := php.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	server, err := bridge.Serve(command)
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	stream, err := server.Ask(bridge.Request{Paths: []string{root}, Contents: contents})
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.New(engine.ReadThrough(contents), stream)

	files := codebase.Files()
	if len(files) != 2 || files[0].Path != existing || files[1].Path != created {
		t.Fatalf("got %d files", len(files))
	}
	classes := codebase.WhereKind("Stmt_Class").Get()
	if len(classes) != 2 {
		t.Fatalf("got %d classes", len(classes))
	}
	span, err := classes[0].Span()
	if err != nil {
		t.Fatal(err)
	}
	if span.Text() != "final class A {}" {
		t.Fatalf("the class spans %q", span.Text())
	}
}
