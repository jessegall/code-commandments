package bridge

import (
	"os"
	"os/exec"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/walk"
)

var footprintOf = func(pid int) int {
	return Footprint(pid)
}

func Footprint(pids ...int) int {
	if len(pids) == 0 {
		return 0
	}

	if walk.Exists("/proc/self/status") {
		return footprintFromProc(pids)
	}

	return footprintFromTop(pids)
}

func footprintFromProc(pids []int) int {
	kilobytes := 0
	for _, pid := range pids {
		status, err := os.ReadFile("/proc/" + strconv.Itoa(pid) + "/status")
		if err != nil {
			continue
		}

		kilobytes += kilobytesOf(string(status), "VmRSS:") + kilobytesOf(string(status), "VmSwap:")
	}

	return kilobytes >> 10
}

func kilobytesOf(status, field string) int {
	for _, line := range strings.Split(status, "\n") {
		if !strings.HasPrefix(line, field) {
			continue
		}

		words := strings.Fields(line)
		if len(words) < 2 {
			return 0
		}

		kilobytes, _ := strconv.Atoi(words[1])

		return kilobytes
	}

	return 0
}

func footprintFromTop(pids []int) int {
	arguments := []string{"-l", "1", "-stats", "mem"}
	for _, pid := range pids {
		arguments = append(arguments, "-pid", strconv.Itoa(pid))
	}

	out, err := exec.Command("top", arguments...).Output()
	if err != nil {
		return 0
	}

	_, sizes, listed := strings.Cut(string(out), "\nMEM")
	if !listed {
		return 0
	}

	kilobytes := 0
	for _, size := range strings.Fields(sizes) {
		kilobytes += kilobytesWritten(size)
	}

	return kilobytes >> 10
}

var topUnits = map[byte]int{'B': 0, 'K': 1, 'M': 1 << 10, 'G': 1 << 20}

func kilobytesWritten(size string) int {
	size = strings.TrimRight(size, "+-")
	if size == "" {
		return 0
	}

	unit, known := topUnits[size[len(size)-1]]
	count, err := strconv.Atoi(size[:len(size)-1])
	if !known || err != nil {
		return 0
	}

	return count * unit
}

// Resident is the memory a process holds in RAM, in MB: cheaper to ask than its Footprint, which on macOS samples
// the process for a second.
func Resident(pid int) int {
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
