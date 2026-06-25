<?php

namespace App\SocketRef;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inbound connect payload and normalizes both endpoints into typed SocketRefs.
 */
final class ConnectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required'],
            'to' => ['required'],
        ];
    }

    public function source(): SocketRef
    {
        return SocketRefData::coalesce($this->input('from'), Direction::Output)->toRef();
    }

    public function target(): SocketRef
    {
        return SocketRefData::coalesce($this->input('to'), Direction::Input)->toRef();
    }
}
