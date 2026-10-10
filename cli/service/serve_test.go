package service_test

import (
	"net"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

// TestARunAsksTheBridgeTheSessionKeepsUp holds a run of the tool to the C# bridge the session keeps up for the
// project: found at the socket named for it, and answering the whole stream of the files asked.
func TestARunAsksTheBridgeTheSessionKeepsUp(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.cs"), []byte("namespace Shop;\n\npublic sealed class Cart { }\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if _, kept := bridge.Service("roslyn", root); kept {
		t.Fatal("a service answers before one is kept up")
	}

	server, err := bridge.Serve(bridgetest.Roslyn(t, root))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	listener, err := net.Listen("unix", bridge.ServiceSocket("roslyn", root))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	go bridge.Answer(listener, server)

	service, kept := bridge.Service("roslyn", root)
	if !kept {
		t.Fatal("the service kept up for the project does not answer")
	}
	defer service.Close()
	stream, err := service.Ask(bridge.Request{Paths: []string{root}})
	if err != nil {
		t.Fatal(err)
	}
	if len(stream.Files) != 1 || stream.Files[0].Path != filepath.Join(root, "Cart.cs") {
		t.Errorf("the service answered %d files", len(stream.Files))
	}
}

// TestAPythonReadAsksTheWarmMypySession holds a read of Python to the mypy session the journal keeps up for the
// project: with the session answering at its socket and no interpreter to start one of its own, the read is still
// whole, so it came from the session.
func TestAPythonReadAsksTheWarmMypySession(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "cart.py"), []byte("def total(prices: list[int]) -> int:\n    return sum(prices)\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	server, err := bridge.Serve(bridgetest.Mypy(t))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	listener, err := net.Listen("unix", bridge.ServiceSocket("mypy", root))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	go bridge.Answer(listener, server)

	t.Setenv("COMMANDMENTS_MYPY_PYTHON", filepath.Join(root, "no-python-here"))
	was, _ := os.Getwd()
	os.Chdir(root)
	defer os.Chdir(was)

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Only(source.Python).Load()
	if err != nil {
		t.Fatalf("the read did not reach the warm session: %v", err)
	}
	if files := codebase.Files(); len(files) != 1 || files[0].Path != filepath.Join(root, "cart.py") {
		t.Errorf("the warm session answered %d files", len(files))
	}
}
