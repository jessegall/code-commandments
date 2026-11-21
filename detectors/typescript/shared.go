package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
)

// language is what TypeScript states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.TypeScript
}

var (
	SideTableTraitDetector                = shared.Bind[shared.SideTableTrait, language]()
	DuplicatedMechanismDetector           = shared.BindGrouped[shared.DuplicatedMechanism, language]()
	DivergentTwinDetector                 = shared.BindGrouped[shared.DivergentTwin, language]()
	ListMirrorsFolderDetector             = shared.Bind[shared.ListMirrorsFolder, language]()
	RepeatedFolderPathDetector            = shared.Bind[shared.RepeatedFolderPath, language]()
)
