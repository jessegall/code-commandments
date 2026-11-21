package backend

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
)

// language is what PHP states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.PHP
}

var (
	InterfaceOverlapDetector              = shared.BindInterfaceOverlaps[language](`\`, false)
	SideTableTraitDetector                = shared.Bind[shared.SideTableTrait, language]()
	DuplicatedMechanismDetector           = shared.BindGrouped[shared.DuplicatedMechanism, language]()
	DivergentTwinDetector                 = shared.BindGrouped[shared.DivergentTwin, language]()
	ListMirrorsFolderDetector             = shared.Bind[shared.ListMirrorsFolder, language]()
	RepeatedFolderPathDetector            = shared.Bind[shared.RepeatedFolderPath, language]()
)

func usesDynamicMembers(codebase *engine.Codebase, access engine.Match) bool {
	return packages.Excuses(codebase, packages.DynamicMembers, php.TypesOf(codebase).TypeAtRead(access.Receiver()), "")
}

func isContractMethod(match engine.Match) bool {
	return packages.Excuses(match.Codebase(), packages.ContractMethod, php.EnclosingClassName(match), php.EnclosingFunctionName(match))
}

func buildsValidationResponse(codebase *engine.Codebase, thrown engine.Match) bool {
	return thrown.Kind() == "Expr_StaticCall" && packages.Excuses(codebase, packages.ValidationResponse,
		strings.TrimLeft(thrown.Child("class").Name(), `\`), thrown.Child("name").Name())
}
