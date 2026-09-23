# The warehouse client gives up after its retries with a bare Exception that carries the story.


def reserve(client, sku: str, quantity: int, retries: int = 3) -> str:
    for _ in range(retries):
        reply = client.post("/reserve", sku=sku, quantity=quantity)
        if reply.ok:
            return reply.reference
    # @sin MessageStringRaise
    raise Exception(f"the warehouse refused {quantity} x {sku} after {retries} attempts")
