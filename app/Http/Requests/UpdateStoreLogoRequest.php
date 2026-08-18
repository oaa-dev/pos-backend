<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']];
    }
}
