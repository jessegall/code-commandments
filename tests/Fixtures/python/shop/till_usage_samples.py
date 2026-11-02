"""The last measurement taken of each till's own machine, replaced as new ones arrive.

A store whose absence is honest, which the registry rule must leave alone. A till that has only just come up has
no sample yet, and `None` there says exactly that: no measurement, which is a different fact from a measured zero.
Every caller passes the absence on rather than deciding it — the report shown to the shop hides a figure it does
not have — so nothing here is asking the same question over and over, and nothing is owed a named exception. It
was report #815 that named this shape.
"""

from dataclasses import dataclass, field


@dataclass(frozen=True)
class UsageSample:
    percent_busy: int
    taken_at: float


@dataclass
class TillUsageSamples:
    latest: dict[str, UsageSample] = field(default_factory=dict)

    def took(self, till: str, sample: UsageSample) -> None:
        self.latest[till] = sample

    # @righteous NullableRegistryLookup
    def sample_of(self, till: str) -> UsageSample | None:
        return self.latest.get(till)

    def busy_percent(self, till: str) -> int | None:
        """The absence travels to the boundary, where a reader is shown nothing rather than a nought."""
        sample = self.sample_of(till)
        if sample is None:
            return None

        return sample.percent_busy


def busiest(samples: TillUsageSamples, tills: list[str]) -> str | None:
    measured = [(till, samples.sample_of(till)) for till in tills]
    busy = [(till, sample) for till, sample in measured if sample is not None]
    if not busy:
        return None

    return max(busy, key=lambda pair: pair[1].percent_busy)[0]
