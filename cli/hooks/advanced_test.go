package hooks

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
)

func warmFor(t *testing.T, root string, settings config.Config) {
	t.Helper()

	Serves()
	t.Cleanup(func() { serving.Store(false) })

	Warming(root, settings)

	for until := time.Now().Add(time.Minute); warming(t) && time.Now().Before(until); {
		time.Sleep(20 * time.Millisecond)
	}
}
