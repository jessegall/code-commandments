<?php

declare(strict_types=1);

namespace App\Fixtures\AnchorEnumComparison;

use App\Support\Enums\CompareSelf;

enum Status
{
    use CompareSelf;

    case A;
    case B;
    case C;
}

final class Descriptor
{
    public Status $type;          // non-nullable

    public ?Status $maybe = null; // nullable
}

final class Sample
{
    private Status $status;       // non-nullable property

    public function flagParam(Status $s): bool
    {
        // FLAG: $s is a non-nullable Status — anchor on it.
        return Status::equalsAny($s, Status::A, Status::B);
    }

    public function leaveNullableParam(?Status $s): bool
    {
        // LEAVE: $s may be null — the static form is the null-safe shape.
        return Status::equalsAny($s, Status::A, Status::B);
    }

    public function flagThisProp(): bool
    {
        // FLAG: $this->status is a non-nullable Status property.
        return Status::equalsAny($this->status, Status::A, Status::B);
    }

    public function flagVarProp(Descriptor $d): bool
    {
        // FLAG: $d->type resolves to a non-nullable Status property.
        return Status::equalsAny($d->type, Status::A, Status::B);
    }

    public function leaveNullableProp(Descriptor $d): bool
    {
        // LEAVE: $d->maybe is nullable.
        return Status::equalsAny($d->maybe, Status::A, Status::B);
    }

    public function leaveSingular(Status $s): bool
    {
        // LEAVE: singular equals is not a set helper.
        return Status::equals($s, Status::A);
    }
}
