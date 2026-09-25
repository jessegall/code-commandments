package csharp

import (
	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Bridge is bridge/roslyn: built once per version of its sources under the cache folder, and run with --tree.
type Bridge struct{}

// Here is the bridge this build carries.
func Here() Bridge {
	return Bridge{}
}

// Stream is the stream the bridge writes for its arguments: paths, and any of its flags.
func (Bridge) Stream(arguments ...string) (*contract.Stream, error) {
	command, err := bridge.Roslyn()
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
