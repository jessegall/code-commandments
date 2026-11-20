from typing import Protocol


class CouponSource(Protocol):
    label: str


class CounterCouponSource(CouponSource):
    label = "Counter"


class NewsletterCouponSource(CouponSource):
    label = "Newsletter"


class AppCouponSource(CouponSource):
    label = "App"


class ReferralCouponSource(CouponSource):
    label = "Referral"


class CatalogueCouponSource(CouponSource):
    label = "Catalogue"


def all_types() -> list[type[CouponSource]]:
    # @sin ListMirrorsFolder
    return [CounterCouponSource, NewsletterCouponSource, AppCouponSource, ReferralCouponSource, CatalogueCouponSource]


# @righteous ListMirrorsFolder
def starter_types() -> list[type[CouponSource]]:
    return [CounterCouponSource, NewsletterCouponSource]
