// Command pack writes a folder as the archive a bundle carries: go run ./bundle/pack <archive> <folder>.
package main

import (
	"fmt"
	"os"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

func main() {
	if len(os.Args) != 3 {
		fmt.Fprintln(os.Stderr, "usage: pack <archive> <folder>")
		os.Exit(2)
	}
	files, err := bundle.Files(os.DirFS(os.Args[2]), ".")
	if err == nil {
		var archive []byte
		if archive, err = bundle.Pack(files); err == nil {
			err = os.WriteFile(os.Args[1], archive, 0o644)
		}
	}
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}
