package bridge

import (
	_ "embed"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

//go:generate npm --prefix frontend run build
//go:generate go run ./bundle/pack bundles/php.tar.gz php
//go:generate go run ./bundle/pack bundles/frontend.tar.gz frontend/dist

//go:embed bundles/php.tar.gz
var phpArchive []byte

//go:embed bundles/frontend.tar.gz
var frontendArchive []byte

// PHP is bridge/php with the php-parser it loads, run by the php on the PATH.
var PHP = bundle.Archived("php", phpArchive)

// Frontend is bridge/frontend's built script and the TypeScript libraries it reads, run by the node on the PATH.
var Frontend = bundle.Archived("frontend", frontendArchive)
