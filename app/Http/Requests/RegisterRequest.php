<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|min:5',
            'email' => 'required|string|email|max:200|unique:users',
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase() // mayus y minus
                    ->numbers() // un numero
                    ->symbols(), // al menos un simbolo
            ],
        ];
    }
}
