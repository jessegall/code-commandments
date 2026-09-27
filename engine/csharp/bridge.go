package csharp

import (
	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Bridge is bridge/roslyn, which writes the generic tree: the executable the tool runs, or, in development, a capped
// container of its image.
type Bridge struct{}

// Here is the bridge this build carries.
func Here() Bridge {
	return Bridge{}
}

// Stream is the stream the bridge writes for its paths, in a run of its own.
func (Bridge) Stream(paths ...string) (*contract.Stream, error) {
	command, err := bridge.Roslyn(paths...)
	if err != nil {
		return nil, err
	}

	return bridge.Once(command, paths...)
}

// Scan is the codebase the bridge reads at paths.
func (b Bridge) Scan(paths ...string) (*engine.Codebase, error) {
	stream, err := b.Stream(paths...)
	if err != nil {
		return nil, err
	}

	return engine.Load(stream), nil
}
