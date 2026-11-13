package hooks

import (
	"time"

	"github.com/jessegall/code-commandments/cli/judge"
)

const rememberedSavedEvery = time.Minute

func rememberedAround(root string, warn func(...string)) (saved func()) {
	remembered, keeps := judge.RememberedFor(root, warn)
	if !keeps {
		return func() {}
	}

	remembered.Load()

	return remembered.Save
}
