<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SearchBookByIsbnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'digits:13'],
        ];
    }

    /**
     * バリデーションメッセージを取得します。
     */
    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNは13桁で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
        ];
    }

    /**
     * バリデーション前に、URL の ISBN をリクエストに追加します。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['isbn' => $this->route('isbn')]);
    }

    /**
     * バリデーションに失敗した場合は、400 とエラーメッセージを返します。
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['error' => $validator->errors()->first('isbn')], 400)
        );
    }
}
