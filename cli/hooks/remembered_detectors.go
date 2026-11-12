package hooks

import (
	"os"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/judge"
)

const rememberedSavedEvery = time.Minute

func rememberedAround(root string, warn func(...string)) (saved func(), alone bool) {
	remembered, keeps := judge.RememberedFor(root, warn)
	if !keeps {
		return func() {}, true
	}

	release, alone := remembered.ReadAlone()
	if !alone {
		return func() {}, false
	}

	remembered.Load()

	return func() {
		remembered.Save()
		release()
	}, true
}

func remembersAcrossRestarts(console cli.Console) (saved func()) {
	remembersTreesAcrossRestarts()

	if os.Getenv(pluginData) == "" {
		return func() {}
	}

	remembered, keeps := judge.RememberedFor(servedRoot(), console.Warn)
	if !keeps {
		return func() {}
	}

	remembered.Load()
	save := remembered.Save

	go func() {
		for range time.Tick(rememberedSavedEvery) {
			save()
		}
	}()

	go func() {
		<-cli.StopSignals()
		logServiceStopped("by a signal")
		save()
		os.Exit(0)
	}()

	return save
}
