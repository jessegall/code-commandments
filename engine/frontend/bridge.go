// Package frontend is the engine's Vue and TypeScript side: the bridge that parses both into the generic tree
// through the TypeScript compiler and Vue's own parser.
package frontend

import (
	"fmt"
	"os/exec"
	"path/filepath"
	"runtime"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

//go:generate npm --prefix ../../bridge/frontend run build

// Bridge is bridge/frontend: its bundled script, and the node that runs it.
type Bridge struct {
	Script string
	Node   string
}

// Here is the bridge in this checkout, run by the node on PATH.
func Here() Bridge {
	_, source, _, _ := runtime.Caller(0)

	return Bridge{Script: filepath.Join(filepath.Dir(source), "..", "..", "bridge", "frontend", "dist", "bridge.mjs"), Node: "node"}
}

// Command is the command that runs the bridge: the node found on PATH, and the script.
func (b Bridge) Command() ([]string, error) {
	node, err := exec.LookPath(b.Node)
	if err != nil {
		return nil, fmt.Errorf("the frontend bridge needs node, and %q is not on PATH: %w", b.Node, err)
	}

	return []string{node, b.Script}, nil
}

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	command, err := b.Command()
	if err != nil {
		return nil, err
	}

	return bridge.Once(command, arguments...)
}

// Scan is the codebase the bridge reads at paths.
func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}

	return engine.Load(stream), nil
}
