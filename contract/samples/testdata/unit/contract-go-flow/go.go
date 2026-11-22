package shop

func scan(rows [][]int, ready chan int, quit chan struct{}) int {
	// @contract kind/assignment
	// @contract fact/assignment/mode = "define"
	// @label totalBind binding
	total := 0
// @contract kind/label
// @contract role/label/binding -> outerB
// @label outerB binding
// @contract role/label/statement -> outerLoop
outer:
	// @label outerLoop loop
	// @contract role/loop/iterable -> rowsRef
	// @label rowsRef reference 22
	// @contract role/loop/body -> loopBody
	// @label loopBody block
	for _, row := range rows {
		for _, cell := range row {
			// @contract kind/block
			// @contract role/block/statements -> [cont]
			if cell < 0 {
				// @contract kind/continue
				// @contract role/continue/label -> contLabel
				// @label contLabel reference
				// @label cont continue
				continue outer
			}
			if cell > 100 {
				// @contract kind/break
				// @contract role/break/label -> brkLabel
				// @label brkLabel reference
				break outer
			}
			total += cell
		}
	}
	// @contract role/if/initializer -> init17
	// @label init17 assignment
	// @contract role/if/then -> then17
	// @label then17 block
	// @contract kind/binary
	// @contract fact/binary/operator = "add"
	// @contract kind/comparison
	// @contract fact/comparison/operator = "greater"
	// @contract fact/comparison/operators = ["greater"]
	// @contract role/comparison/operands -> [nextRef, ten]
	// @label nextRef reference 24
	// @label ten literal 31
	// @contract link/comparison/left -> nextRef
	// @contract link/comparison/right -> ten
	if next := total + 1; next > 10 {
		// @contract kind/goto
		// @contract role/goto/destination -> doneRef
		// @label doneRef reference
		// @contract link/goto/label -> doneLabel
		goto done
	}
	// @contract kind/defer
	// @contract role/defer/call -> deferCall
	// @label deferCall call
	// @contract kind/call
	// @contract role/call/callee -> closeRef
	// @label closeRef reference 8
	// @contract role/call/arguments -> [quitArg]
	// @label quitArg argument
	// @contract kind/argument
	// @contract role/argument/value -> quitRef
	// @label quitRef reference 14
	defer close(quit)
	// @contract kind/select
	// @contract role/select/arms -> [selRecv, selDefault]
	select {
	// @contract kind/select-arm
	// @label selRecv select-arm
	// @contract role/select-arm/communication -> comm
	// @label comm expression-statement
	// @contract role/select-arm/body -> selBody
	// @contract kind/unary
	// @contract fact/unary/operator = "receive"
	// @contract role/unary/operand -> readyRef
	// @label readyRef reference 18
	case value := <-ready:
		total += value // @label selBody block
	// @contract kind/select-arm
	// @label selDefault select-arm
	default:
	}
	// @contract kind/function
	// @contract kind/unsupported at 2
	// @contract fact/unsupported/construct at 2 = "go"
	// @contract role/unsupported/parts at 2 -> [goCall]
	// @label goCall call
	go func() { ready <- 1 }()
	// @contract kind/unsupported
	// @contract fact/unsupported/construct = "send"
	ready <- 2
	// @contract kind/no-op
	;
// @contract kind/label
// @label doneLabel label
// @contract role/label/binding -> doneB
// @label doneB binding
// @contract role/label/statement -> doneRet
done:
	// @label doneRet return
	// @contract link/reference/binding -> totalBind
	return total
}
