package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/shared"
	_ "github.com/jessegall/code-commandments/engine/golang"
)

// language is what Go states about judging the shared rules: the engine it judges them under.
type language struct{}

func (language) Engine() catalog.Engine {
	return catalog.Go
}

var (
	InterfaceOverlapDetector              = shared.BindInterfaceOverlaps[language]("/", true)
	DivergentTwinDetector                 = shared.BindGrouped[shared.DivergentTwin, language]()
	DuplicatedMechanismDetector           = shared.BindGrouped[shared.DuplicatedMechanism, language]()
	RepeatedFolderPathDetector            = shared.Bind[shared.RepeatedFolderPath, language]()
)
