package git

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
)

var remembered sync.Map

var asked struct {
	sync.Mutex
	count int
}

func counted() {
	asked.Lock()
	asked.count++
	asked.Unlock()
}

func Asked() int {
	asked.Lock()
	defer asked.Unlock()

	return asked.count
}

func recalling(key string, state func() string, ask func() string) string {
	now := state()

	if held, known := remembered.Load(key); known {
		if was := held.([2]string); was[0] == now {
			return was[1]
		}
	}

	said := ask()
	remembered.Store(key, [2]string{now, said})

	return said
}

func headState(root string) string {
	own := filepath.Join(root, ".git")
	if gitdir := gitDirOf(root); gitdir != "" {
		own = gitdir
	}

	head := filepath.Join(own, "HEAD")
	state := markOf(head)

	common := own
	if filepath.Base(filepath.Dir(own)) == worktrees {
		common = filepath.Dir(filepath.Dir(own))
	}

	state += "\x00" + markOf(filepath.Join(common, "packed-refs"))

	raw, err := os.ReadFile(head)
	if err != nil {
		return state
	}

	if ref, named := strings.CutPrefix(strings.TrimSpace(string(raw)), "ref: "); named {
		return state + "\x00" + markOf(filepath.Join(common, strings.TrimSpace(ref)))
	}

	return state
}

func markOf(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().String() + "\x00" + strconv.FormatInt(info.Size(), 10)
}
