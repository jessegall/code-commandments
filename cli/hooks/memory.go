package hooks

import (
	"fmt"
	"os"
	"os/exec"
	"runtime"
	"runtime/debug"
	"strconv"
	"strings"
	"sync"
)

const serviceMemoryLimit = 3 << 30

func limitServiceMemory() {
	if os.Getenv("GOMEMLIMIT") == "" {
		debug.SetMemoryLimit(serviceMemoryLimit)
	}
}

// Memory is what a memory figure of this plugin means, taken three ways because the three differ by gigabytes: the
// Go heap this process has, this process's own resident size, and the resident size of this process with every
// bridge it keeps up. The language bridges hold the most and are in none of the Go numbers, so a figure that does
// not say which of the three it is says almost nothing.
type Memory struct {
	HeapMB        int
	ResidentMB    int
	WithBridgesMB int
}

var highest struct {
	sync.Mutex
	held Memory
}

func MemoryHighest() Memory {
	highest.Lock()
	defer highest.Unlock()

	return highest.held
}

func memoryNoted(held Memory) {
	highest.Lock()
	defer highest.Unlock()

	if held.WithBridgesMB > highest.held.WithBridgesMB {
		highest.held = held
	}
}

func MemoryNow() Memory {
	var stats runtime.MemStats
	runtime.ReadMemStats(&stats)

	held := Memory{HeapMB: int(stats.HeapAlloc >> 20)}
	held.ResidentMB = residentOf(os.Getpid())
	held.WithBridgesMB = held.ResidentMB + bridgesResident()

	memoryNoted(held)

	return held
}

func (m Memory) Line() string {
	return fmt.Sprintf("heap=%dMB resident=%dMB withBridges=%dMB", m.HeapMB, m.ResidentMB, m.WithBridgesMB)
}

func residentOf(pid int) int {
	if held := residentFromProc(pid); held > 0 {
		return held
	}

	out, err := exec.Command("ps", "-o", "rss=", "-p", strconv.Itoa(pid)).Output()
	if err != nil {
		return 0
	}

	kilobytes, err := strconv.Atoi(strings.TrimSpace(string(out)))
	if err != nil {
		return 0
	}

	return kilobytes / 1024
}

func residentFromProc(pid int) int {
	read, err := os.ReadFile("/proc/" + strconv.Itoa(pid) + "/statm")
	if err != nil {
		return 0
	}

	fields := strings.Fields(string(read))
	if len(fields) < 2 {
		return 0
	}

	pages, err := strconv.Atoi(fields[1])
	if err != nil {
		return 0
	}

	return pages * os.Getpagesize() >> 20
}

func bridgesResident() int {
	held := 0
	for _, pid := range bridgePids() {
		held += residentOf(pid)
	}

	return held
}

func bridgePids() []int {
	out, err := exec.Command("pgrep", "-P", strconv.Itoa(os.Getpid())).Output()
	if err != nil {
		return nil
	}

	var pids []int
	for _, line := range strings.Fields(string(out)) {
		if pid, err := strconv.Atoi(line); err == nil {
			pids = append(pids, pid)
		}
	}

	return pids
}
