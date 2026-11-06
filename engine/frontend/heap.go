package frontend

import (
	"os"
	"strconv"
	"strings"
)

var cgroupLimits = []string{"/sys/fs/cgroup/memory.max", "/sys/fs/cgroup/memory/memory.limit_in_bytes"}

const heapShare = 75

const unlimited = 1 << 50

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
