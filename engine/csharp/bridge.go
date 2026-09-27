package csharp

import (
	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Bridge is bridge/roslyn, a prebuilt image writing the generic tree: the service a session keeps up, or a memory-capped
// container of its own for one run, the paths it reads mounted there. .NET never runs on the host.
type Bridge struct{}

// Here is the bridge this build carries.
func Here() Bridge {
	return Bridge{}
}

// Stream is the stream the bridge writes for its paths: from the service the session keeps up for their project, or
// else from a container of its own for this run.
func (Bridge) Stream(paths ...string) (*contract.Stream, error) {
	if service, ok := bridge.RoslynService(paths...); ok {
		defer service.Close()

		return service.Ask(bridge.Request{Paths: paths})
	}
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
