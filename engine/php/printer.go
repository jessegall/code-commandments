package php

import (
	"github.com/jessegall/code-commandments/engine"
)

type printer struct {
	rename func(name engine.Match) (string, bool)
}

var magicConstants = map[string]string{
	"Scalar_MagicConst_Class": "__CLASS__", "Scalar_MagicConst_Dir": "__DIR__", "Scalar_MagicConst_File": "__FILE__",
	"Scalar_MagicConst_Function": "__FUNCTION__", "Scalar_MagicConst_Line": "__LINE__", "Scalar_MagicConst_Method": "__METHOD__",
	"Scalar_MagicConst_Namespace": "__NAMESPACE__", "Scalar_MagicConst_Trait": "__TRAIT__", "Scalar_MagicConst_Property": "__PROPERTY__",
}

func literalText(node engine.Match) (string, bool) {
	if node.Node() == nil || node.Node().Value == nil {
		return "", false
	}

	return node.Node().Value.Text()
}
