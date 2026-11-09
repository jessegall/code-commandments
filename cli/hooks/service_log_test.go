package hooks

import (
	"bytes"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
)

func capturedServiceLog(t *testing.T, served bool) *bytes.Buffer {
	t.Helper()

	before, wasServing := serviceLog, serving.Load()
	t.Cleanup(func() {
		serviceLog = before
		serving.Store(wasServing)
	})

	captured := &bytes.Buffer{}
	serviceLog = captured
	serving.Store(served)

	return captured
}

func TestAJudgingIsWrittenToTheServiceLog(t *testing.T) {
	captured := capturedServiceLog(t, true)

	Record(t.TempDir(), Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project", Sins: 3})

	if said := captured.String(); !strings.Contains(said, "file_edited") || !strings.Contains(said, "sins=3") {
		t.Errorf("the service log says: %q", said)
	}
}

func TestABridgeTheServiceStartsIsWrittenToTheServiceLog(t *testing.T) {
	captured := capturedServiceLog(t, true)

	bridge.Run([]string{"true"}, "one", "two")

	said := captured.String()
	for _, named := range []string{"started", "bridge", "true", "arguments=2"} {
		if !strings.Contains(said, named) {
			t.Errorf("the service log does not say %q: %s", named, said)
		}
	}
}

func TestAFailedRuleIsWrittenToTheServiceLogWithItsStack(t *testing.T) {
	captured := capturedServiceLog(t, true)

	logRuleFailure("a-rule", "nil pointer")

	said := captured.String()
	for _, named := range []string{"rule failed", "a-rule", "nil pointer", "goroutine"} {
		if !strings.Contains(said, named) {
			t.Errorf("the service log does not say %q: %s", named, said)
		}
	}
}

func TestAOneShotHookWritesNothingToTheServiceLog(t *testing.T) {
	captured := capturedServiceLog(t, false)

	logRuleFailure("a-rule", "nil pointer")

	if captured.Len() != 0 {
		t.Errorf("a hook outside the service wrote: %s", captured)
	}
}
