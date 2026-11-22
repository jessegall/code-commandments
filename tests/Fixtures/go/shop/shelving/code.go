package shelving

import (
	"strconv"
	"strings"
)

type Code struct {
	Aisle string
	Slot  int
}

func Parse(written string) Code {
	aisle, slot, _ := strings.Cut(written, "-")
	number, _ := strconv.Atoi(slot)

	return Code{Aisle: aisle, Slot: number}
}

func (c Code) Label() string {
	return c.Aisle + "/" + strconv.Itoa(c.Slot)
}
