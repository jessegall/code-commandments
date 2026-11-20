//go:build !commandments_release

package contract

import "flag"

func checksSchema() bool {
	return flag.Lookup("test.v") != nil
}
