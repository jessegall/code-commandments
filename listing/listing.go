// Package listing lists a folder's entries in the order the PHP tool met them.
package listing

import (
	"hash/crc32"
	"os"
	"slices"
	"strings"
	"unicode/utf8"
)

var castagnoli = crc32.MakeTable(crc32.Castagnoli)

func Of(dir string) []string {
	folder, err := os.Open(dir)
	if err != nil {
		return nil
	}

	defer folder.Close()

	names, _ := folder.Readdirnames(-1)

	return Ordered(names)
}

func Ordered(names []string) []string {
	slices.SortStableFunc(names, func(a, b string) int {
		if ha, hb := hash(a), hash(b); ha != hb {
			return int(ha) - int(hb)
		}

		return strings.Compare(a, b)
	})

	return names
}

func hash(name string) uint32 {
	folded := strings.ToLower(name)
	units := make([]byte, 0, 4*utf8.RuneCountInString(folded))

	for _, r := range folded {
		units = append(units, byte(r), byte(r>>8), byte(r>>16), byte(r>>24))
	}

	return ^crc32.Checksum(units, castagnoli) & 0x3FFFFF
}
