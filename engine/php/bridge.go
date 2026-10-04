// Package php is the engine's PHP side: the bridge that parses PHP into the generic tree, and the
// analyses the engine fills into it (resolved types, call targets) and asks of it.
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

// Bridge is bridge/php: its sources, the PHP that runs them, and the folder it keeps each file's tree in.
type Bridge struct {
	Sources bundle.Bundle
	PHP     string
	// Trees is where the bridge keeps each file's tree between runs, to write again while the file is unchanged;
	// none when empty.
	Trees string
}

// Here is the bridge this binary carries, run by the php on PATH.
func Here() Bridge {
	return Bridge{Sources: bridge.PHP, PHP: "php"}
}

// quiet are the settings the bridge runs under: a coverage or debugging extension the user's PHP loads, pcov or
// Xdebug, instruments every call the parse makes and has nothing to record, and a PHP without one ignores them.
var quiet = []string{"-d", "pcov.enabled=0", "-d", "xdebug.mode=off"}

// Command is the command that runs the bridge: the PHP found on PATH, quieted, and the script written out of the
// binary.
func (b Bridge) Command() ([]string, error) {
	php, err := exec.LookPath(b.PHP)
	if err != nil {
		return nil, bridge.ToolchainMissing{Language: "PHP", Need: b.PHP + " on the PATH"}
	}
	folder, err := b.Sources.Folder()
	if err != nil {
		return nil, err
	}

	command := append(append([]string{php}, quiet...), filepath.Join(folder, "bridge.php"))
	if b.Trees != "" {
		command = append(command, "--cache="+b.Trees)
	}

	return command, nil
}

// Cached is the bridge keeping each file's tree in the user's cache folder, beside the bundles written out there.
func (b Bridge) Cached() Bridge {
	if trees, err := bundle.TreesFolder("php"); err == nil {
		b.Trees = trees
	}

	return b
}

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	command, err := b.Command()
	if err != nil {
		return nil, err
	}
	stream, failure, ran, err := bridge.Run(command, arguments...)
	if !ran {
		return nil, errors.Join(fmt.Errorf("the PHP bridge failed: %w", err), errors.New(failure))
	}

	return stream, err
}

// Scan is the codebase the bridge reads at paths, as Codebase::scan reads it, with the facts the engine fills for
// PHP filled: each expression's resolved type and each call's target.
func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}
	codebase := engine.Load(stream)
	TypesOf(codebase).Fill(codebase)

	return codebase, nil
}

// Over is the codebase a served bridge answers the request with, its sources read through the contents the request
// carries, with the facts the engine fills for PHP filled.
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
