package roslyn_test

import (
	"net"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/cli/roslyn"
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
	if _, kept := bridge.RoslynService(root); kept {
		t.Fatal("a service answers before one is kept up")
	}

	server, err := bridge.Serve(bridgetest.Roslyn(t, root))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	listener, err := net.Listen("unix", bridge.RoslynSocket(root))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	go roslyn.Answer(listener, server)

	service, kept := bridge.RoslynService(root)
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
