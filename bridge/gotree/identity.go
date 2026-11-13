package gotree

import (
	"crypto/sha256"
	"embed"
	"encoding/hex"
	"io/fs"
	"runtime"
	"strconv"
	"sync"
)

//go:embed *.go
var sources embed.FS

// Identity names the Go bridge by its own sources, the contract version it writes and the Go it reads with, so a release
// that leaves the bridge alone keeps every line it read.
var Identity = sync.OnceValue(func() string {
	digest := sha256.New()
	digest.Write([]byte(runtime.Version() + "\x00" + strconv.Itoa(contractVersion) + "\x00"))
	names, _ := fs.Glob(sources, "*.go")
	for _, name := range names {
		text, _ := sources.ReadFile(name)
		digest.Write([]byte(name + "\x00"))
		digest.Write(text)
	}

	return "go-" + hex.EncodeToString(digest.Sum(nil))[:16]
})
