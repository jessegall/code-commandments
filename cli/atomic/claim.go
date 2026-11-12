package atomic

import (
	"crypto/rand"
	"encoding/hex"
	"os"
	"path/filepath"
	"time"
)

// Claim is a sentinel file one run makes for itself and removes when it is done, so a second run sees the first is busy and steps aside rather than waits. While held it is touched again and again, so a long run keeps it however long it reads; a claim nobody touched for longer than its expiry counts as gone, so a run that crashed with it in place blocks the others for that long at most. Only the run that made it removes it: each claim holds a token of its own.
type Claim struct {
	path   string
	expiry time.Duration
}

func ClaimAt(path string, expiry time.Duration) Claim {
	return Claim{path: path, expiry: expiry}
}

func (c Claim) Take() (release func(), taken bool) {
	if token, made := c.made(); made {
		return c.held(token), true
	}

	if info, err := os.Stat(c.path); err != nil || time.Since(info.ModTime()) < c.expiry {
		return func() {}, false
	}

	os.Remove(c.path)

	if token, made := c.made(); made {
		return c.held(token), true
	}

	return func() {}, false
}

func (c Claim) held(token string) func() {
	done := make(chan struct{})

	go func() {
		ticker := time.NewTicker(c.expiry / 3)
		defer ticker.Stop()

		for {
			select {
			case <-done:
				return
			case now := <-ticker.C:
				if c.owns(token) {
					os.Chtimes(c.path, now, now)
				}
			}
		}
	}()

	return func() {
		close(done)
		if c.owns(token) {
			os.Remove(c.path)
		}
	}
}

func (c Claim) owns(token string) bool {
	written, err := os.ReadFile(c.path)

	return err == nil && string(written) == token
}

func (c Claim) made() (string, bool) {
	os.MkdirAll(filepath.Dir(c.path), 0o777)

	file, err := os.OpenFile(c.path, os.O_CREATE|os.O_EXCL|os.O_WRONLY, 0o666)
	if err != nil {
		return "", false
	}
	defer file.Close()

	random := make([]byte, 16)
	rand.Read(random)
	token := hex.EncodeToString(random)
	file.WriteString(token)

	return token, true
}
