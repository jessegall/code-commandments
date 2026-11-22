package shop

type Job struct{}

func (Job) Run() {} // @contract promise/obligation-across-files -> one.go#base
