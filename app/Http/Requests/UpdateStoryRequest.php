<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is enforced by StoryPolicy through authorizeResource().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * An update is a partial edit of an existing story, not a re-create, so
     * nothing is required. The field names are the ones the model can actually
     * fill -- inheriting the create rules demanded title and content, neither of
     * which is fillable, so a "valid" update quietly changed nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'prompt' => 'sometimes|nullable|string',
        ];
    }
}
