package frontend

import (
	"os"
	"path/filepath"
	"testing"
)

// TestTheAllowanceIsReadFromTheLimitThatIsEnforced holds the bridge to the memory a container really grants
// rather than the memory the machine reports: node reads the machine's and caps itself far under what it was
// given — 1584 MB inside a 3 GB container — which is what takes a thousand-file frontend out of memory.
func TestTheAllowanceIsReadFromTheLimitThatIsEnforced(t *testing.T) {
	for name, limit := range map[string]struct {
		written string
		want    int
	}{
		"a three gigabyte container":      {"3221225472\n", 2304},
		"a capped half gigabyte":          {"536870912", 384},
		"a hierarchy capping nothing":     {"max", 0},
		"the number v1 writes for no cap": {"9223372036854771712", 0},
		"a file holding nonsense":         {"plenty", 0},
	} {
		t.Run(name, func(t *testing.T) {
			folder := t.TempDir()
			path := filepath.Join(folder, "memory.max")

			if err := os.WriteFile(path, []byte(limit.written), 0o644); err != nil {
				t.Fatal(err)
			}

			kept := cgroupLimits
			cgroupLimits = []string{path}
			defer func() { cgroupLimits = kept }()

			if got := heapAllowance(); got != limit.want {
				t.Errorf("%q allows %d MB, want %d", limit.written, got, limit.want)
			}
		})
	}
}

// TestNothingIsSaidWhereTheMachineCapsNothing holds the change to where node is demonstrably wrong: on a machine
// with no limit enforced, node reads real memory and its own answer is the right one.
func TestNothingIsSaidWhereTheMachineCapsNothing(t *testing.T) {
	kept := cgroupLimits
	cgroupLimits = []string{filepath.Join(t.TempDir(), "absent")}
	defer func() { cgroupLimits = kept }()

	if got := heapAllowance(); got != 0 {
		t.Errorf("an uncapped machine is told %d MB; node's own answer should stand", got)
	}
}

// TestTheUsersOwnAllowanceWins holds NODE_OPTIONS above ours: someone who has said how much heap node may have
// has said it for a reason, and a second flag would quietly take it back.
func TestTheUsersOwnAllowanceWins(t *testing.T) {
	folder := t.TempDir()
	path := filepath.Join(folder, "memory.max")

	if err := os.WriteFile(path, []byte("3221225472"), 0o644); err != nil {
		t.Fatal(err)
	}

	kept := cgroupLimits
	cgroupLimits = []string{path}
	defer func() { cgroupLimits = kept }()
	t.Setenv("NODE_OPTIONS", "--max-old-space-size=900")

	if got := heapAllowance(); got != 0 {
		t.Errorf("the user's own NODE_OPTIONS was overruled with %d MB", got)
	}
}
