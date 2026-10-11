package frontend

import (
	"os"
	"strconv"
	"strings"
)

// cgroupLimits are where a container's memory limit is written, newest first: a v2 unified hierarchy, then v1.
var cgroupLimits = []string{"/sys/fs/cgroup/memory.max", "/sys/fs/cgroup/memory/memory.limit_in_bytes"}

// heapShare is how much of a container's memory the bridge may hold. The rest is for the tool reading its stream,
// for node itself outside the heap, and for the headroom that makes a clean failure out of what would otherwise
// be the kernel killing the process.
const heapShare = 75

// unlimited is what a cgroup writes where it caps nothing, and a value so large it means the same.
const unlimited = 1 << 50

// heapAllowance is the megabytes the bridge may hold, or none where nothing should be said. Node reads how much
// memory the MACHINE has and caps its heap at a fraction of that, which is right on a laptop and wrong inside a
// container: there it is told one number and held to another, and it lands far under what it was granted — 1584
// MB of a 3 GB container, where a thousand-file frontend needs about 2 GB. So the allowance is read from the
// limit that is really enforced, and only where one is; on a machine that caps nothing, node's own answer stands.
func heapAllowance() int {
	if strings.Contains(os.Getenv("NODE_OPTIONS"), "--max-old-space-size") {
		return 0
	}

	for _, path := range cgroupLimits {
		limit, read := os.ReadFile(path)
		if read != nil {
			continue
		}

		bytes, err := strconv.ParseInt(strings.TrimSpace(string(limit)), 10, 64)
		if err != nil || bytes <= 0 || bytes >= unlimited {
			continue
		}

		return int(bytes >> 20 * heapShare / 100)
	}

	return 0
}
