# The FIX for the copied encoders: one function takes the content type that differed, and each named
# encoder passes its own.

from shop.encoders import ByteStream


# @fixed NearDuplicateFunction
def encode(text: str, kind: str) -> tuple[dict[str, str], ByteStream]:
    body = text.encode("utf-8")
    headers = {"Content-Length": str(len(body)), "Content-Type": f"{kind}; charset=utf-8"}
    return headers, ByteStream(body)


# @fixed NearDuplicateFunction
def encode_text(text: str) -> tuple[dict[str, str], ByteStream]:
    return encode(text, "text/plain")


# @fixed NearDuplicateFunction
def encode_html(html: str) -> tuple[dict[str, str], ByteStream]:
    return encode(html, "text/html")
