// Command roslyn-name prints the image name the C# bridge's sources are built as: a digest of every source file that goes into the image.
package main

import (
	"fmt"
	"os"

	"github.com/jessegall/code-commandments/bridge"
)

func main() {
	name, err := bridge.RoslynSourcesImage(".")
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}

	fmt.Println(name)
}
