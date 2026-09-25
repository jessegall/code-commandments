package php

import (
	"fmt"
	"math"
	"math/big"
	"strconv"
	"strings"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/engine"
)

// Printed is an expression as php-parser's standard pretty printer writes it, for the constant expressions a rewrite
// reprints: literals, constants, a negation, `self::CONST` and `new self(...)`. A name rename answers for is written
// as it answers; any other kind of expression is refused.
func Printed(expression engine.Match, rename func(name engine.Match) (string, bool)) (string, error) {
	return (printer{rename: rename}).print(expression)
}

type printer struct {
	rename func(name engine.Match) (string, bool)
}

func (p printer) print(node engine.Match) (string, error) {
	kind := node.Kind()
	switch {
	case strings.HasPrefix(kind, "Name"):
		return p.name(node), nil
	case kind == "Identifier":
		return node.Name(), nil
	case kind == "Scalar_String":
		return printedString(node)
	case kind == "Scalar_Int":
		return printedInt(node)
	case kind == "Scalar_Float":
		return printedFloat(node)
	case strings.HasPrefix(kind, "Scalar_MagicConst_"):
		return magicConstants[kind], nil
	case kind == "Expr_ConstFetch":
		return p.print(node.Child("name"))
	case kind == "Expr_ClassConstFetch":
		class, err := p.print(node.Child("class"))
		if err != nil {
			return "", err
		}
		constant, err := p.print(node.Child("name"))

		return class + "::" + constant, err
	case kind == "Expr_UnaryMinus":
		operand, err := p.print(node.Child("expr"))
		if strings.HasPrefix(operand, "-") {
			operand = "(" + operand + ")"
		}

		return "-" + operand, err
	case kind == "Expr_New":
		return p.construction(node)
	}

	return "", fmt.Errorf("php-parser's printer is ported for constant expressions only, not %s", kind)
}

// name is a name as written after name resolution: fully qualified with its backslash, relative with `namespace\`.
func (p printer) name(node engine.Match) string {
	if renamed, ok := p.rename(node); ok {
		return renamed
	}
	switch node.Kind() {
	case "Name_FullyQualified":
		return `\` + node.Name()
	case "Name_Relative":
		return `namespace\` + node.Name()
	}

	return node.Name()
}

// construction is `new Class(args)`, the arguments on one line, each as `name: ...value`.
func (p printer) construction(node engine.Match) (string, error) {
	class, err := p.print(node.Child("class"))
	if err != nil {
		return "", err
	}
	var arguments []string
	for _, argument := range node.ChildrenIn("args") {
		if argument.Kind() != "Arg" || len((Node{Match: argument}).Comments()) > 0 {
			return "", fmt.Errorf("php-parser's printer is ported for plain arguments only")
		}
		value, err := p.print(argument.Child("value"))
		if err != nil {
			return "", err
		}
		prefix := ""
		if name := argument.Child("name"); name.Exists() {
			prefix = name.Name() + ": "
		}
		for _, flag := range argument.Node().Flags {
			switch flag {
			case "by-ref":
				prefix += "&"
			case "spread":
				prefix += "..."
			}
		}
		arguments = append(arguments, prefix+value)
	}

	return "new " + class + "(" + strings.Join(arguments, ", ") + ")", nil
}

var magicConstants = map[string]string{
	"Scalar_MagicConst_Class": "__CLASS__", "Scalar_MagicConst_Dir": "__DIR__", "Scalar_MagicConst_File": "__FILE__",
	"Scalar_MagicConst_Function": "__FUNCTION__", "Scalar_MagicConst_Line": "__LINE__", "Scalar_MagicConst_Method": "__METHOD__",
	"Scalar_MagicConst_Namespace": "__NAMESPACE__", "Scalar_MagicConst_Trait": "__TRAIT__", "Scalar_MagicConst_Property": "__PROPERTY__",
}

// printedString is a string single-quoted, or double-quoted with its escapes when written so; a heredoc is refused.
func printedString(node engine.Match) (string, error) {
	written := sourceOf(node)
	value, ok := literalText(node)
	if !ok {
		return "", fmt.Errorf("a string with no value")
	}
	switch {
	case strings.HasPrefix(written, `"`) || strings.HasPrefix(written, `b"`) || strings.HasPrefix(written, `B"`):
		return `"` + escapedString(value) + `"`, nil
	case strings.HasPrefix(written, "<<<"):
		return "", fmt.Errorf("php-parser's printer is not ported for heredocs")
	}

	return singleQuoted(value), nil
}

// singleQuoted escapes a quote, a backslash before a quote, a backslash or the end, and a backslash after one.
func singleQuoted(value string) string {
	var out strings.Builder
	out.WriteByte('\'')
	for i := 0; i < len(value); i++ {
		c := value[i]
		escape := c == '\'' ||
			c == '\\' && (i+1 == len(value) || value[i+1] == '\'' || value[i+1] == '\\') ||
			c == '\\' && i > 0 && value[i-1] == '\\'
		if escape {
			out.WriteByte('\\')
		}
		out.WriteByte(c)
	}
	out.WriteByte('\'')

	return out.String()
}

// escapedString is a value as a double-quoted string holds it: addcslashes of the escapable bytes, then control
// characters and bytes outside valid UTF-8 as `\xHH`.
func escapedString(value string) string {
	var slashed strings.Builder
	for i := 0; i < len(value); i++ {
		switch c := value[i]; c {
		case '\n':
			slashed.WriteString(`\n`)
		case '\r':
			slashed.WriteString(`\r`)
		case '\t':
			slashed.WriteString(`\t`)
		case '\f':
			slashed.WriteString(`\f`)
		case '\v':
			slashed.WriteString(`\v`)
		case '$', '"', '\\':
			slashed.WriteByte('\\')
			slashed.WriteByte(c)
		default:
			slashed.WriteByte(c)
		}
	}
	escaped := slashed.String()
	var out strings.Builder
	for i := 0; i < len(escaped); {
		c := escaped[i]
		if c <= 0x08 || c >= 0x0E && c <= 0x1F {
			fmt.Fprintf(&out, `\x%02X`, c)
			i++

			continue
		}
		if c < 0x80 {
			out.WriteByte(c)
			i++

			continue
		}
		r, size := utf8.DecodeRuneInString(escaped[i:])
		if r == utf8.RuneError && size <= 1 {
			fmt.Fprintf(&out, `\x%02X`, c)
			i++

			continue
		}
		out.WriteString(escaped[i : i+size])
		i += size
	}

	return out.String()
}

// printedInt is an integer in the base it was written in.
func printedInt(node engine.Match) (string, error) {
	written := strings.ToLower(strings.ReplaceAll(sourceOf(node), "_", ""))
	text, _ := literalText(node)
	value, ok := new(big.Int).SetString(text, 10)
	if !ok {
		return "", fmt.Errorf("an integer with no value")
	}
	switch {
	case strings.HasPrefix(written, "0x"):
		return "0x" + value.Text(16), nil
	case strings.HasPrefix(written, "0b"):
		return "0b" + value.Text(2), nil
	case len(written) > 1 && strings.HasPrefix(written, "0"):
		return "0" + value.Text(8), nil
	}

	return value.String(), nil
}

// printedFloat is a float at the shortest of 16 or 17 significant digits that holds it, always with a point or an
// exponent, as PHP's %G writes it.
func printedFloat(node engine.Match) (string, error) {
	value, err := strconv.ParseFloat(strings.ReplaceAll(sourceOf(node), "_", ""), 64)
	if err != nil {
		return "", err
	}
	switch {
	case math.IsInf(value, 1):
		return "1.0E+1000", nil
	case math.IsInf(value, -1):
		return "-1.0E+1000", nil
	case math.IsNaN(value):
		return `\NAN`, nil
	}
	text := phpG(value, 16)
	if parsed, _ := strconv.ParseFloat(text, 64); parsed != value {
		text = phpG(value, 17)
	}
	if isInteger(text) {
		text += ".0"
	}

	return text, nil
}

// phpG is PHP's sprintf('%.<precision>G'): scientific when the exponent is below -4 or reaches the precision, its
// mantissa always carrying a point, trailing zeros dropped otherwise.
func phpG(value float64, precision int) string {
	if value == 0 {
		if math.Signbit(value) {
			return "-0"
		}

		return "0"
	}
	scientific := strconv.FormatFloat(value, 'E', precision-1, 64)
	mantissa, exponentText, _ := strings.Cut(scientific, "E")
	exponent, _ := strconv.Atoi(exponentText)
	sign := ""
	if strings.HasPrefix(mantissa, "-") {
		sign, mantissa = "-", mantissa[1:]
	}
	digits := strings.TrimRight(strings.Replace(mantissa, ".", "", 1), "0")
	if digits == "" {
		digits = "0"
	}
	if exponent < -4 || exponent >= precision {
		rest := digits[1:]
		if rest == "" {
			rest = "0"
		}
		exponentSign := "+"
		if exponent < 0 {
			exponentSign, exponent = "-", -exponent
		}

		return sign + digits[:1] + "." + rest + "E" + exponentSign + strconv.Itoa(exponent)
	}
	if exponent < 0 {
		return sign + "0." + strings.Repeat("0", -exponent-1) + digits
	}
	if len(digits) <= exponent+1 {
		return sign + digits + strings.Repeat("0", exponent+1-len(digits))
	}

	return sign + digits[:exponent+1] + "." + digits[exponent+1:]
}

func isInteger(text string) bool {
	digits := strings.TrimPrefix(text, "-")
	if digits == "" {
		return false
	}
	for _, c := range digits {
		if c < '0' || c > '9' {
			return false
		}
	}

	return true
}

// literalText is the value a string or integer literal holds, as the tree writes it.
func literalText(node engine.Match) (string, bool) {
	if node.Node().Value == nil {
		return "", false
	}

	return node.Node().Value.Text()
}
