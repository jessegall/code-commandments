// Package frontend is the engine's Vue and TypeScript side: the bridge that parses both into the generic tree
// through the TypeScript compiler and Vue's own parser.
package frontend

import (
	"bytes"
	"errors"
	"fmt"
	"os/exec"
	"path/filepath"
	"runtime"

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

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	node, err := exec.LookPath(b.Node)
	if err != nil {
		return nil, fmt.Errorf("the frontend bridge needs node, and %q is not on PATH: %w", b.Node, err)
	}
	var out, failure bytes.Buffer
	command := exec.Command(node, append([]string{b.Script}, arguments...)...)
	command.Stdout = &out
	command.Stderr = &failure
	if err := command.Run(); err != nil {
		return nil, errors.Join(fmt.Errorf("the frontend bridge failed: %w", err), errors.New(failure.String()))
	}

	return contract.ReadAll(&out)
}

// Scan is the codebase the bridge reads at paths.
func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}

	return engine.Load(stream), nil
}
