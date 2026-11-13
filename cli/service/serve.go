package service

import (
	"errors"
	"fmt"
	"net"
	"os"
	"path/filepath"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/hooks"
)

const (
	pluginData         = "JOURNAL_PLUGIN_DATA"
	journalEnvironment = "JOURNAL_ENV"
	stoppingWithin     = 3 * time.Second
)

// Serve is `roslyn-serve`, `mypy-serve`: the bridge kept up for the project.
type Serve struct {
	Bridge
}

func (s Serve) Names() []string {
	return []string{s.Kind + "-serve"}
}

func (s Serve) Help() help.Help {
	return help.Of("Keep the "+s.Label+" bridge running for this project, answering each run of the tool over a socket named for the project.").
		Form(s.Kind+"-serve", "serve until the bridge stops (started by the journal as a plugin service)").
		Note("A project with no " + s.Label + " to judge keeps no bridge: the service waits until it is stopped, fetching and starting nothing.").
		Note("A bridge that cannot run here at all, for want of its own toolchain, waits too, and says once what to install rather than ending and being started again for a thing that will not appear while the session runs.").
		Note("A run of the tool says nothing when it judges through this service, since that is the ordinary way of it, and warns when it reached the service and got no answer. So a run that is slow and silent found no service: the socket is named for the project's canonical path, which `ls /tmp/code-commandments-" + s.Kind + "-*.sock` lists, and a run works the project out from the repository its folder is in.").
		In(help.Hooks)
}

func tell(unavailable bridge.Unavailable, console cli.Console) {
	queue, queued := hooks.Queue4In(os.Getenv(pluginData))
	if !queued {
		return
	}

	words := hooks.Unjudged("a bridge the session keeps up cannot run on this machine, so every rule of that "+
		"language finds nothing", unavailable.Error(), "") + " Tell the user what to install."

	if err := queue.Tell(hooks.JournalAnswer{Whisper: &words}, os.Getenv(journalEnvironment), 0); err != nil {
		console.Warn("what the bridge cannot do could not be told to the journal: " + err.Error())
	}
}

func stopOn(stopped <-chan os.Signal, listener net.Listener) {
	<-stopped
	fmt.Fprintln(os.Stderr, "this service stopped by a signal")
	listener.Close()
	bridge.StopEveryBridge()
	time.Sleep(stoppingWithin)
	os.Exit(0)
}

func (s Serve) Run(in *cli.Input, console cli.Console) (int, error) {
	stopped := cli.StopSignals()
	defer health.Serve(s.Kind)()

	project, holds, err := here(s.Bridge)
	if err != nil {
		return 0, err
	}
	if !holds {
		console.Say(project + " has no " + s.Label + " to judge, so no " + s.Label + " bridge is kept up for it.")
		<-stopped

		return 0, nil
	}

	if data := os.Getenv(pluginData); data != "" {
		bundle.ForgetUnused(filepath.Join(data, s.Kind+"-cache"), time.Now())
	}

	bridged, needed, err := s.Reach.Own(s.Bridge, []string{project})
	if unavailable := bridge.Unavailable(nil); errors.As(err, &unavailable) {
		console.Warn(unavailable.Error())
		tell(unavailable, console)
		<-stopped

		return 0, nil
	}
	if err != nil {
		console.Warn(err.Error())

		return 1, nil
	}
	if !needed {
		console.Say(s.Label + " needs no bridge of its own, so none is kept up for " + project + ".")
		<-stopped

		return 0, nil
	}
	if notice := s.Notice(); notice != "" {
		console.Warn(notice)
	}
	path := bridge.ServiceSocket(s.Kind, project)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		bridged.Close()
		console.Warn("Cannot listen on " + path + ": " + err.Error())

		return 1, nil
	}
	defer listener.Close()
	os.Chmod(path, 0o600)

	go stopOn(stopped, listener)

	return s.answering(listener, project, bridged, console), nil
}

func (s Serve) answering(listener net.Listener, project string, bridged bridge.Asker, console cli.Console) int {
	for {
		go warm(s.Bridge, project, bridged, console)

		gone := bridge.Answer(listener, bridged)
		bridged.Close()

		if gone == nil {
			return 0
		}

		console.Warn("The " + s.Label + " bridge is gone, so this service starts a new one: " + gone.Error())

		var err error
		if bridged, _, err = s.Reach.Own(s.Bridge, []string{project}); err != nil {
			console.Warn(err.Error())

			return 1
		}
	}
}
