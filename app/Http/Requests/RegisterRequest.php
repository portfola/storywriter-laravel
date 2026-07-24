<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Store emails lowercased so "Ada@example.com" and "ada@example.com" can't
     * become two accounts, and so the case-insensitive lookup at login has
     * something consistent to match against.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => Str::lower($this->email)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Compared case-insensitively rather than with `unique:users,email`,
            // because accounts created before emails were normalised may still
            // be stored with mixed case.
            'email' => ['required', 'email', 'max:255', function (string $attribute, mixed $value, callable $fail) {
                if (User::whereRaw('LOWER(email) = ?', [Str::lower((string) $value)])->exists()) {
                    $fail('An account with this email address already exists.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }
}
