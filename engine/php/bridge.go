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

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (b Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	php, err := exec.LookPath(b.PHP)
	if err != nil {
		return nil, fmt.Errorf("the PHP bridge needs PHP, and %q is not on PATH: %w", b.PHP, err)
	}
	var out, failure bytes.Buffer
	command := exec.Command(php, append([]string{b.Script}, arguments...)...)
	command.Stdout = &out
	command.Stderr = &failure
	if err := command.Run(); err != nil {
		return nil, errors.Join(fmt.Errorf("the PHP bridge failed: %w", err), errors.New(failure.String()))
	}

	return contract.ReadAll(&out)
}

// Scan is the codebase the bridge reads at paths, as Codebase::scan reads it.
func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}

	return engine.Load(stream), nil
}
