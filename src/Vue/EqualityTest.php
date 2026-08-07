<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A `subject === 'literal'` test, read off the parsed expression: WHAT is being tested, and the
 * literal it is tested against. The two are one answer — a subject with no case, or a case with no
 * subject, says nothing — so they are one type rather than a pair the caller destructures.
 */
final readonly class EqualityTest
{
    public function __construct(
        public string $subject,
        public string $key,
    ) {}

    /**
     * Does this test the SAME subject as $other — which is what makes two branches one switch?
     */
    public function sharesSubjectWith(self $other): bool
    {
        return $this->subject === $other->subject;
    }
}
