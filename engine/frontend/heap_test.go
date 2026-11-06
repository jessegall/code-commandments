package frontend

import (
	"os"
	"path/filepath"
	"testing"
)

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

func TestNothingIsSaidWhereTheMachineCapsNothing(t *testing.T) {
	kept := cgroupLimits
	cgroupLimits = []string{filepath.Join(t.TempDir(), "absent")}
	defer func() { cgroupLimits = kept }()

	if got := heapAllowance(); got != 0 {
		t.Errorf("an uncapped machine is told %d MB; node's own answer should stand", got)
	}
}

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
