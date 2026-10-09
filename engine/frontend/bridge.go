// Package frontend is the engine's Vue and TypeScript side: the bridge that parses both into the generic tree
// through the TypeScript compiler and Vue's own parser.
package frontend

import (
	"os/exec"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Bridge is bridge/frontend: its built script, the node that runs it, and the folder it keeps each file's tree in.
type Bridge struct {
	Sources bundle.Bundle
	Node    string
	// Trees is where the bridge keeps each file's tree between runs, to write again while nothing it was made from
	// has changed; none when empty.
	Trees string
}

// Here is the bridge this binary carries, run by the node on PATH.
func Here() Bridge {
	return Bridge{Sources: bridge.Frontend, Node: "node"}
}

// Command is the command that runs the bridge: the node found on PATH, and the script written out of the binary.
func (b Bridge) Command() ([]string, error) {
	node, err := exec.LookPath(b.Node)
	if err != nil {
		return nil, bridge.ToolchainMissing{Language: "Vue and TypeScript", Need: b.Node + " on the PATH"}
	}
	folder, err := b.Sources.Folder()
	if err != nil {
		return nil, err
	}

	command := []string{node, filepath.Join(folder, "bridge.mjs")}
	if b.Trees != "" {
		command = append(command, "--cache="+b.Trees)
	}

	return command, nil
}

// Cached is the bridge keeping each file's tree in the user's cache folder, beside the bundles written out there.
func (b Bridge) Cached() Bridge {
	if trees, err := bundle.TreesFolder("frontend"); err == nil {
		b.Trees = trees
	}

	return b
}

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	return b.StreamTallied(func() {}, arguments...)
}

// StreamTallied is the stream the bridge writes for its arguments, telling tally of each file the moment it is read.
func (b Bridge) StreamTallied(tally func(), arguments ...string) (*contract.Stream, error) {
	command, err := b.Command()
	if err != nil {
		return nil, err
	}

	return bridge.OnceTallied(command, tally, arguments...)
}

// Scan is the codebase the bridge reads at paths.
func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}

	return engine.Load(stream), nil
}

// Over is the codebase a served bridge answers the request with, its sources read through the contents the request
// carries.
func Over(server *bridge.Server, request bridge.Request) (*engine.Codebase, error) {
	stream, err := server.Ask(request)
	if err != nil {
		return nil, err
	}

	bridge.InWalkOrder(stream, request.Paths)

	return engine.New(engine.ReadThrough(request.Contents), stream), nil
}
