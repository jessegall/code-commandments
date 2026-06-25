# OptionDiscipline corpus — a fake project

A self-contained mini-app the `OptionDisciplineProphet` runs against end-to-end,
with real cross-file call graphs (so the `min_callers` gate and `CodebaseIndex`
resolve genuine callers). One clearly-named scenario per verdict.

| Scenario | Verdict | Why |
|---|---|---|
| `Adopt/UserDirectory.php` | **FLAG (A)** | `findByEmail(): User\|null` decides null; two callers each `=== null` it. |
| `AdoptSuppressed/DraftStore.php` | silent | Decides null, but a single caller — below `min_callers`. |
| `NeverNone/Clock.php` | **FLAG (B)** | `: Option` whose every return is `Option::some(...)`. |
| `WrapUnwrap/Greeter.php` | **FLAG (D)** | `Option::some($x)->unwrap()`. |
| `Justified/SchemaShapes.php` | silent | `: Option` with a real `none()` path; callers unwrap/branch — **normal**. The regression lock for the retired smell #3 contradiction. |
| `NullRight/RateProvider.php` | silent | Cache-miss null consumed by `?? default` — bare null is right. |
| `Exempt/RequestParser.php` | silent | Request-boundary parser — the HTTP null idiom. |

`expected.php` is the exact manifest `OptionDisciplineCorpusTest` asserts against.
Eyeball it with `php tests/Fixtures/option-discipline/check.php`. To regenerate
`expected.php` after an intentional change, run check.php and translate its output.
