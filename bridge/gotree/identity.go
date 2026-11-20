package gotree

import (
	"crypto/sha256"
	"encoding/hex"
	"runtime"
	"strconv"
	"sync"
)

var Identity = sync.OnceValue(func() string {
	digest := sha256.Sum256([]byte(runtime.Version() + "\x00" + strconv.Itoa(contractVersion) + "\x00" + sourceDigest()))

	return "go-" + hex.EncodeToString(digest[:8])
})
