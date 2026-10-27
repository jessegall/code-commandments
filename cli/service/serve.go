package service

import (
	"net"
	"os"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/health"
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
	// Before anything else, so the journal's health question is answered from the moment this process is up and
	// however this run ends up spending its life: a bridge kept warm, or a service with no language to judge
	// waiting for its stop, is a service that is up either way. It answers on a listener of its own, so a question
	// never waits behind the parse the bridge is in the middle of.
	defer health.Serve(s.Kind)()

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

	bridged, needed, err := s.Reach.Own(s.Bridge, []string{project})
	if err != nil {
		console.Warn(err.Error())

		return 1, nil
	}
	if !needed {
		stopped := cli.StopSignals()
		console.Say(s.Label + " needs no bridge of its own, so none is kept up for " + project + ".")
		<-stopped

		return 0, nil
	}
	if notice := s.Notice(); notice != "" {
		console.Warn(notice)
	}
	defer bridged.Close()

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
	go warm(s.Bridge, project, bridged, console)

	if err := bridge.Answer(listener, bridged); err != nil {
		console.Warn(err.Error())

		return 1, nil
	}

	return 0, nil
}
