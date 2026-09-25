// Package php is the engine's PHP side: the bridge that parses PHP into the generic tree, and the
// analyses the engine fills into it (resolved types, call targets) and asks of it.
package php

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

// Bridge is bridge/php: a PHP script, and the PHP that runs it.
type Bridge struct {
	Script string
	PHP    string
}

// Here is the bridge in this checkout, run by the php on PATH.
func Here() Bridge {
	_, source, _, _ := runtime.Caller(0)

	return Bridge{Script: filepath.Join(filepath.Dir(source), "..", "..", "bridge", "php", "bridge.php"), PHP: "php"}
}

// Command is the command that runs the bridge: the PHP found on PATH, and the script.
func (b Bridge) Command() ([]string, error) {
	php, err := exec.LookPath(b.PHP)
	if err != nil {
		return nil, fmt.Errorf("the PHP bridge needs PHP, and %q is not on PATH: %w", b.PHP, err)
	}

	return []string{php, b.Script}, nil
}

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	command, err := b.Command()
	if err != nil {
		return nil, err
	}
	var out, failure bytes.Buffer
	process := exec.Command(command[0], append(command[1:], arguments...)...)
	process.Stdout = &out
	process.Stderr = &failure
	if err := process.Run(); err != nil {
		return nil, errors.Join(fmt.Errorf("the PHP bridge failed: %w", err), errors.New(failure.String()))
	}

	return contract.ReadAll(&out)
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
