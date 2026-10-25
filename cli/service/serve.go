package service

import (
	"net"
	"os"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

// Serve is `roslyn-serve`, `mypy-serve`: the bridge kept up for the project.
type Serve struct {
	Bridge
}

// Names are the verbs it answers to.
func (s Serve) Names() []string {
	return []string{s.Kind + "-serve"}
}

// Help documents it.
func (s Serve) Help() help.Help {
	return help.Of("Keep the "+s.Label+" bridge running for this project, answering each run of the tool over a socket named for the project.").
		Form(s.Kind+"-serve", "serve until the bridge stops (started by the journal as a plugin service)").
		Note("A project with no " + s.Label + " to judge keeps no bridge: the service waits until it is stopped, fetching and starting nothing.").
		In(help.Hooks)
}

// Run serves the project's bridge until it stops, then ends, so the journal starts it again.
func (s Serve) Run(in *cli.Input, console cli.Console) (int, error) {
	project, holds, err := here(s.Bridge)
	if err != nil {
		return 0, err
	}
	if !holds {
		stopped := cli.StopSignals()
		console.Say(project + " has no " + s.Label + " to judge, so no " + s.Label + " bridge is kept up for it.")
		<-stopped

		return 0, nil
	}

	command, err := s.Start([]string{project})
	if err != nil {
		console.Warn(err.Error())

		return 1, nil
	}
	if notice := s.Notice(); notice != "" {
		console.Warn(notice)
	}

	server, err := bridge.Serve(command)
	if err != nil {
		console.Warn(err.Error())

		return 1, nil
	}
	defer server.Close()

	path := bridge.ServiceSocket(s.Kind, project)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		console.Warn("Cannot listen on " + path + ": " + err.Error())

		return 1, nil
	}
	defer listener.Close()
	os.Chmod(path, 0o600)

	// Behind the listener, never in front of it: the socket answers from this moment, and the reading of the
	// project happens in the time the service would otherwise spend idle.
	go warm(s.Bridge, project, server, console)

	if err := bridge.Answer(listener, server); err != nil {
		console.Warn(err.Error())

		return 1, nil
	}

	return 0, nil
}
