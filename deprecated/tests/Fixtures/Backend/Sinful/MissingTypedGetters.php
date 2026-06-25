<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// This is a warning-level check - FormRequest with rules but no typed getters
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'phone' => 'nullable|string',
        ];
    }

    // Missing typed getters like getName(), getEmail(), etc.
}
