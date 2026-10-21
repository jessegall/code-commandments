package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestABlockingCallInACoroutineIsFoundAndAnAwaitIsNot holds the rule to the fault it names: a call that stops
// its thread, made inside an async def, where that thread is the event loop. The asynchronous counterpart, the
// same call handed to a thread, and the same call in a plain def are how the work is done right and must pass.
func TestABlockingCallInACoroutineIsFoundAndAnAwaitIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"sleeping on the loop":         {"import time\n\n\nasync def wait() -> None:\n    time.sleep(1)\n", 1},
		"a blocking request":           {"import requests\n\n\nasync def read(url: str) -> str:\n    return requests.get(url).text\n", 1},
		"a subprocess on the loop":     {"import subprocess\n\n\nasync def run(command: str) -> None:\n    subprocess.run(command, check=True)\n", 1},
		"the asynchronous counterpart": {"import asyncio\n\n\nasync def wait() -> None:\n    await asyncio.sleep(1)\n", 0},
		"handed to a thread":           {"import asyncio\nimport time\n\n\nasync def wait() -> None:\n    await asyncio.to_thread(lambda: time.sleep(1))\n", 0},
		"the same call in a plain def": {"import time\n\n\ndef wait() -> None:\n    time.sleep(1)\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/waiting.py": test.source})
			found := (python.BlockedAwaitDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
