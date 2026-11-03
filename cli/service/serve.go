package service

import (
	"errors"
	"net"
	"os"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/hooks"
)

const (
	// pluginData is the folder the journal gives its plugin, which a service is given as a hook is: the queue the
	// journal drains at every one of the agent's hooks lives inside it.
	pluginData = "JOURNAL_PLUGIN_DATA"
	// journalEnvironment is the environment the journal started this service for, so what it has to say reaches
	// that environment's agent rather than another's.
	journalEnvironment = "JOURNAL_ENV"
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
		Note("A bridge that cannot run here at all, for want of its own toolchain, waits too, and says once what to install rather than ending and being started again for a thing that will not appear while the session runs.").
		Note("A run of the tool says nothing when it judges through this service, since that is the ordinary way of it, and warns when it reached the service and got no answer. So a run that is slow and silent found no service: the socket is named for the project's canonical path, which `ls /tmp/code-commandments-" + s.Kind + "-*.sock` lists, and a run works the project out from the repository its folder is in.").
		In(help.Hooks)
}

// tell puts what a service cannot do where the user will read it. A service's own words go nowhere: the journal
// sends its output to /dev/null, so a bridge that says on its console why it can judge nothing is a bridge saying
// it to no one, and the project then lists a service as running that judges nothing, which reads as a plugin that
// found nothing rather than one that looked at nothing.
//
// It is said through the queue the journal already drains at every one of the agent's hooks, which a service
// reaches because it is given the same data folder a hook is. A journal that names no folder drains nothing, and
// then this costs the service nothing and changes nothing: the words are still on its console for whoever reads
// one.
func tell(unavailable bridge.Unavailable, console cli.Console) {
	queue, queued := hooks.Queue4In(os.Getenv(pluginData))
	if !queued {
		return
	}

	words := "A bridge the session keeps up cannot run on this machine, so part of this project is judged by nothing: " +
		unavailable.Error() + ". Tell the user what to install; until then every rule of that language finds nothing, " +
		"which is not the same as finding no sins."

	// No agent: a service is nobody's, and what it has to say is for whichever agent next reads the queue.
	if err := queue.Tell(hooks.JournalAnswer{Whisper: &words}, os.Getenv(journalEnvironment), 0); err != nil {
		console.Warn("what the bridge cannot do could not be told to the journal: " + err.Error())
	}
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
	// A bridge that cannot run on this machine at all - no node, no php, no docker - is in the same position as a
	// project with no language to judge: it says so once and waits to be stopped, answering the journal's health
	// question all the while. Ending here instead asks the journal to start it again, and `restart: always` does,
	// for a condition that cannot change while the session runs: the service then fails over and over, is recorded
	// as a fault and listed as stopped, while its `when` command keeps passing, because the project really does
	// have the language. Every run of the tool then pays the bridge's whole start-up, which on a large tree is
	// seconds of it, and nothing says why.
	if unavailable := bridge.Unavailable(nil); errors.As(err, &unavailable) {
		stopped := cli.StopSignals()
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
