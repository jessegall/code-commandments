package php

import ()

var selfContained = map[string]bool{
	"Expr_Variable": true, "Expr_PropertyFetch": true, "Expr_NullsafePropertyFetch": true, "Expr_StaticPropertyFetch": true,
	"Expr_ArrayDimFetch": true, "Expr_FuncCall": true, "Expr_MethodCall": true, "Expr_NullsafeMethodCall": true,
	"Expr_StaticCall": true, "Expr_ConstFetch": true, "Expr_ClassConstFetch": true, "Expr_Isset": true, "Expr_Empty": true,
}

var inverse = map[string]string{
	"Expr_BinaryOp_Identical":    "!==",
	"Expr_BinaryOp_NotIdentical": "===",
	"Expr_BinaryOp_Equal":        "!=",
	"Expr_BinaryOp_NotEqual":     "==",
}
