package shop

func kind(k Kind) string {
	return k.Food // @contract promise/read-declaration-resolves -> one.go#food
}
