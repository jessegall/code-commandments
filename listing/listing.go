// Package listing lists a folder's entries in the order the PHP tool met them. The PHP tool walked a folder
// in the order the filesystem listed it, and its recordings were made on macOS, whose APFS lists a folder by
// a hash of each name; ordering by that hash here makes every walk meet files in that one order on every
// filesystem, where a raw listing follows ext4's seeded hash or tmpfs's creation order.
package listing

import (
	"hash/crc32"
	"os"
	"slices"
	"strings"
	"unicode/utf8"
)

var castagnoli = crc32.MakeTable(crc32.Castagnoli)

// Of are the names directly inside dir, dotfiles included, in the PHP tool's order; none when dir cannot be
// read.
func Of(dir string) []string {
	folder, err := os.Open(dir)
	if err != nil {
		return nil
	}

	defer folder.Close()

	names, _ := folder.Readdirnames(-1)

	return Ordered(names)
}

// Ordered sorts names as APFS lists a folder: by the name's hash, then by the name itself.
func Ordered(names []string) []string {
	slices.SortStableFunc(names, func(a, b string) int {
		if ha, hb := hash(a), hash(b); ha != hb {
			return int(ha) - int(hb)
		}

		return strings.Compare(a, b)
	})

	return names
}

// hash is the 22 bits APFS keys a directory entry by: the CRC-32C of the case-folded name in UTF-32, left
// uncomplemented. A name macOS would store decomposed (an accented letter) is hashed as it is written.
func hash(name string) uint32 {
	folded := strings.ToLower(name)
	units := make([]byte, 0, 4*utf8.RuneCountInString(folded))

	for _, r := range folded {
		units = append(units, byte(r), byte(r>>8), byte(r>>16), byte(r>>24))
	}

	return ^crc32.Checksum(units, castagnoli) & 0x3FFFFF
}
