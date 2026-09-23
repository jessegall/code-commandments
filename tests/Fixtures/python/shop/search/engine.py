# The search engine renders results inside the storefront's layout, and the storefront imports it back.
from shop.storefront.layout import frame
from shop.storefront.layout import footer


def render(query: str) -> str:
    return frame(query) + footer()
