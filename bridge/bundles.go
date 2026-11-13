package bridge

import (
	_ "embed"
	"sync"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

//go:generate npm --prefix frontend run build
//go:generate go run ./bundle/pack bundles/php.tar.gz php
//go:generate go run ./bundle/pack bundles/frontend.tar.gz frontend/dist

//go:embed bundles/php.tar.gz
var phpArchive []byte

//go:embed bundles/frontend.tar.gz
var frontendArchive []byte

var PHP = bundle.Archived("php", phpArchive)

var Frontend = bundle.Archived("frontend", frontendArchive)

// PHPIdentity and FrontendIdentity name each bridge by the bundle it runs, so a release that changes neither keeps
// what they read.
var PHPIdentity = sync.OnceValue(PHP.Identity)

var FrontendIdentity = sync.OnceValue(Frontend.Identity)
