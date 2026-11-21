package laravel

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// requestHelper is what Laravel's request() helper yields when it is asked for no key: the current request.
var requestHelper = &contract.Type{Kind: "named", Name: Request, Text: Request, Origin: "laravel"}

func init() {
	engine.OffersCallResults(contract.PHP, func(call engine.Match) *contract.Type {
		helper := php.Node{Match: call}
		if !helper.CallsFunction("request") || !helper.IsCalledWithoutArguments() {
			return nil
		}

		return requestHelper
	})
}
