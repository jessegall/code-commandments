package judge

import (
	"os"
	"runtime/pprof"

	"github.com/jessegall/code-commandments/cli"
)

func profileOf(in *cli.Input) string {
	file, _ := in.Option("profile")

	return file
}

func profiled(file string, console cli.Console) (stop func()) {
	if file == "" {
		return func() {}
	}

	written, err := os.Create(file)
	if err != nil {
		console.Warn("no CPU profile is written: " + err.Error())

		return func() {}
	}

	if err := pprof.StartCPUProfile(written); err != nil {
		written.Close()
		console.Warn("no CPU profile is written: " + err.Error())

		return func() {}
	}

	return func() {
		pprof.StopCPUProfile()
		written.Close()
	}
}
