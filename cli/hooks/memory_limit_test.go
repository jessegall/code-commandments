package hooks

import (
	"runtime/debug"
	"testing"
)

func TestTheServiceIsGivenAMemoryLimit(t *testing.T) {
	before := debug.SetMemoryLimit(-1)
	t.Cleanup(func() { debug.SetMemoryLimit(before) })

	t.Setenv("GOMEMLIMIT", "")

	limitServiceMemory()

	if said := debug.SetMemoryLimit(-1); said != serviceMemoryLimit {
		t.Errorf("the service runs with a memory limit of %d bytes", said)
	}
}

func TestAMemoryLimitNamedInTheEnvironmentIsLeftAlone(t *testing.T) {
	before := debug.SetMemoryLimit(-1)
	t.Cleanup(func() { debug.SetMemoryLimit(before) })

	t.Setenv("GOMEMLIMIT", "1GiB")

	limitServiceMemory()

	if said := debug.SetMemoryLimit(-1); said != before {
		t.Errorf("a limit named in the environment was replaced with %d bytes", said)
	}
}
