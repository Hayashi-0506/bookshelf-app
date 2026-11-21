<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class UpdateReviewRequest extends StoreReviewRequest
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
     * 更新時は書籍の重複チェックを行わないため、book のルールを除外します。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), 'book');
    }

    /**
     * 更新時は書籍の重複チェックを行わないため、何もしません。
     */
    protected function prepareForValidation(): void
    {
        //
    }
}
