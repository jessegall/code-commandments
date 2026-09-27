// Package roslyn is `roslyn-serve`: the C# bridge kept running for a project, answering each run of the tool over a
// socket named for the project, so a judge of C# compiles against references the bridge has already loaded.
package roslyn

import (
	"bufio"
	"encoding/json"
	"errors"
	"net"
	"os"
	"os/signal"
	"syscall"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
)

// Serve is `roslyn-serve`.
type Serve struct{}

// Names are the verbs it answers to.
func (Serve) Names() []string {
	return []string{"roslyn-serve"}
}

// Help documents it.
func (Serve) Help() help.Help {
	return help.Of("Keep the C# bridge running for this project, answering each run of the tool over a socket named for the project.").
		Form("roslyn-serve", "serve until the bridge stops (started by the journal as a plugin service)").
		Note("A project with no C# to judge keeps no bridge: the service waits until it is stopped, fetching and starting nothing.").
		In(help.Hooks)
}

// Run serves the project's C# bridge until it stops, then ends, so the journal starts it again.
func (s Serve) Run(in *cli.Input, console cli.Console) (int, error) {
	cwd, _ := os.Getwd()
	project := workspace.ProjectRoot(cwd)

	settings, err := config.Load(project)
	if err != nil {
		return 0, err
	}
	if !settings.Holds(project, source.CSharp) {
		stopped := stopSignals()
		console.Say(project + " has no C# to judge, so no C# bridge is kept up for it.")
		<-stopped

		return 0, nil
	}

	command, err := bridge.Roslyn(project)
	if err != nil {
		console.Warn(err.Error())

		return 1, nil
	}
	if notice := bridge.RoslynNotice(); notice != "" {
		console.Warn(notice)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		console.Warn(err.Error())

		return 1, nil
	}
	defer server.Close()

	path := bridge.RoslynSocket(project)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		console.Warn("Cannot listen on " + path + ": " + err.Error())

		return 1, nil
	}
	defer listener.Close()
	os.Chmod(path, 0o600)

	if err := Answer(listener, server); err != nil {
		console.Warn(err.Error())

		return 1, nil
	}

	return 0, nil
}

// Answer answers each connection's request through the bridge, one at a time, until the listener closes; a bridge
// that fails ends it, answering why.
func Answer(listener net.Listener, server *bridge.Server) error {
	for {
		connection, err := listener.Accept()
		if errors.Is(err, net.ErrClosed) {
			return nil
		}
		if err != nil {
			continue
		}
		if err := answer(connection, server); err != nil {
			return err
		}
	}
}

// answer is one connection: its request line asked of the bridge, and every line of the stream written back.
func answer(connection net.Conn, server *bridge.Server) error {
	defer connection.Close()

	line, err := bufio.NewReader(connection).ReadBytes('\n')
	if err != nil {
		return nil
	}
	var request bridge.Request
	if json.Unmarshal(line, &request) != nil {
		return nil
	}

	return server.AskEach(request, func(_ contract.Line, raw []byte) error {
		connection.Write(raw)
		connection.Write([]byte{'\n'})

		return nil
	})
}

// stopSignals is where the session's stop arrives: a service with nothing to serve waits on it rather than ending, so
// the journal does not start it again.
func stopSignals() <-chan os.Signal {
	stopped := make(chan os.Signal, 1)
	signal.Notify(stopped, syscall.SIGTERM, os.Interrupt)

	return stopped
}
