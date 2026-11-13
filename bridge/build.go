package bridge

import (
	"crypto/sha256"
	"encoding/hex"
	"io"
	"os"
	"sync"
)

// Build names the running executable by its release and the hash of its own bytes, so what one build kept between
// runs is never read by another: every bridge it embeds is part of those bytes.
var Build = sync.OnceValue(func() string {
	path, err := os.Executable()
	if err != nil {
		return Release
	}

	file, err := os.Open(path)
	if err != nil {
		return Release
	}
	defer file.Close()

	digest := sha256.New()
	if _, err := io.Copy(digest, file); err != nil {
		return Release
	}

	return Release + "-" + hex.EncodeToString(digest.Sum(nil)[:12])
})
