//go:build !commandments_release

package gotree

import (
	"crypto/sha256"
	"embed"
	"encoding/hex"
	"io/fs"
)

//go:embed *.go
var sources embed.FS

func sourceDigest() string {
	digest := sha256.New()
	names, _ := fs.Glob(sources, "*.go")
	for _, name := range names {
		text, _ := sources.ReadFile(name)
		digest.Write([]byte(name + "\x00"))
		digest.Write(text)
	}

	return hex.EncodeToString(digest.Sum(nil))
}
