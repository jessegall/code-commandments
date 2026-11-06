// Package frontend is the engine's Vue and TypeScript side: the bridge that parses both into the generic tree through the TypeScript compiler and Vue's own parser.
package frontend

import (
	"os/exec"
	"path/filepath"
	"strconv"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Bridge is bridge/frontend: its built script, the node that runs it, and the folder it keeps each file's tree in.
type Bridge struct {
	Sources bundle.Bundle
	Node    string
	Trees   string
}

func Here() Bridge {
	return Bridge{Sources: bridge.Frontend, Node: "node"}
}

func (b Bridge) Command() ([]string, error) {
	node, err := exec.LookPath(b.Node)
	if err != nil {
		return nil, bridge.ToolchainMissing{Language: "Vue and TypeScript", Need: b.Node + " on the PATH"}
	}
	folder, err := b.Sources.Folder()
	if err != nil {
		return nil, err
	}

	command := []string{node}
	if heap := heapAllowance(); heap > 0 {
		command = append(command, "--max-old-space-size="+strconv.Itoa(heap))
	}
	command = append(command, filepath.Join(folder, "bridge.mjs"))

	if b.Trees != "" {
		command = append(command, "--cache="+b.Trees)
	}

	return command, nil
}

func (b Bridge) Cached() Bridge {
	if trees, err := bundle.TreesFolder("frontend"); err == nil {
		b.Trees = trees
	}

	return b
}

func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	return b.StreamTallied(func() {}, arguments...)
}

func (b Bridge) StreamTallied(tally func(), arguments ...string) (*contract.Stream, error) {
	command, err := b.Command()
	if err != nil {
		return nil, err
	}

	return bridge.OnceTallied(command, tally, arguments...)
}

func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}

	return engine.Load(stream), nil
}

func Over(server *bridge.Server, request bridge.Request) (*engine.Codebase, error) {
	stream, err := server.Ask(request)
	if err != nil {
		return nil, err
	}

	bridge.InWalkOrder(stream, request.Paths)

	return engine.New(engine.ReadThrough(request.Contents), stream), nil
}
