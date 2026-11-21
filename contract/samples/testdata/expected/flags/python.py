async def flags(*args: int, key: str = "", **options: str) -> None:
    async for item in stream():
        pass
    show(*args, key=key, **options)
    yield key


def grouped() -> None:
    try:
        run()
    except* ValueError:
        pass
