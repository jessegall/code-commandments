// Package php is the engine's PHP side: the bridge that parses PHP into the generic tree, and the analyses the engine fills into it (resolved types, call targets) and asks of it.
package php

import (
	"errors"
	"fmt"
	"os/exec"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Bridge is bridge/php: its sources and the PHP that runs them.
type Bridge struct {
	Sources bundle.Bundle
	PHP     string
}

func Here() Bridge {
	return Bridge{Sources: bridge.PHP, PHP: "php"}
}

var quiet = []string{"-d", "pcov.enabled=0", "-d", "xdebug.mode=off"}

func (b Bridge) Command() ([]string, error) {
	php, err := exec.LookPath(b.PHP)
	if err != nil {
		return nil, bridge.ToolchainMissing{Language: "PHP", Need: b.PHP + " on the PATH"}
	}
	folder, err := b.Sources.Folder()
	if err != nil {
		return nil, err
	}

	return append(append([]string{php}, quiet...), filepath.Join(folder, "bridge.php")), nil
}

func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	return b.StreamTallied(func() {}, arguments...)
}

func (b Bridge) StreamTallied(tally func(), arguments ...string) (*contract.Stream, error) {
	command, err := b.Command()
	if err != nil {
		return nil, err
	}
	stream, failure, ran, err := bridge.RunTallied(command, tally, bridge.ListedArguments(arguments...)...)
	if !ran {
		return stream, errors.Join(fmt.Errorf("the PHP bridge failed: %w", err), errors.New(failure))
	}

	return stream, err
}

func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}
	codebase := engine.Load(stream)
	TypesOf(codebase).Fill(codebase)

	return codebase, nil
}

func Over(server *bridge.Server, request bridge.Request) (*engine.Codebase, error) {
	stream, err := server.Ask(request)
	if err != nil {
		return nil, err
	}
	bridge.InWalkOrder(stream, request.Paths)
	codebase := engine.New(engine.ReadThrough(request.Contents), stream)
	TypesOf(codebase).Fill(codebase)

	return codebase, nil
}
