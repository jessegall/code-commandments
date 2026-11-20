//go:build commandments_release

package gotree

var releaseSourceDigest string

func sourceDigest() string {
	if releaseSourceDigest == "" {
		panic("Go bridge source digest is missing; build with scripts/release/build")
	}

	return releaseSourceDigest
}
