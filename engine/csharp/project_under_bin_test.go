package csharp_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine"
)

func TestAProjectInsideAFolderNamedBinIsReadAndItsOwnBuildOutputIsNot(t *testing.T) {
	parent, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	root := filepath.Join(parent, "bin", "app")
	for _, path := range []string{"src/Order.cs", "obj/Generated.cs", "src/bin/Copied.cs"} {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte("namespace Shop;\n\npublic class Order {}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	stream, err := bridge.Once(bridgetest.Roslyn(t, root), root)
	if err != nil {
		t.Fatal(err)
	}

	files := engine.Load(stream).Files()
	if len(files) != 1 {
		t.Fatalf("read %v, want only src/Order.cs: folders above the project are not build output, and folders inside it are", files)
	}
}
