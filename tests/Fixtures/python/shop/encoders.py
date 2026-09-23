# A response body encoded two ways — and the two encoders are one function written twice, apart from
# the content type each one names.


class ByteStream:
    def __init__(self, body: bytes) -> None:
        self.body = body


# @sin NearDuplicateFunction
def encode_text(text: str) -> tuple[dict[str, str], ByteStream]:
    body = text.encode("utf-8")
    length = str(len(body))
    kind = "text/plain; charset=utf-8"
    headers = {"Content-Length": length, "Content-Type": kind}
    return headers, ByteStream(body)


# @sin NearDuplicateFunction
def encode_html(html: str) -> tuple[dict[str, str], ByteStream]:
    body = html.encode("utf-8")
    length = str(len(body))
    kind = "text/html; charset=utf-8"
    headers = {"Content-Length": length, "Content-Type": kind}
    return headers, ByteStream(body)
