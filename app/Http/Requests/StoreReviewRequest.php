<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'in:1,2,3,4,5'],
            'comment' => ['nullable', 'string'],
            'book' => [
                Rule::unique('reviews', 'book_id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * バリデーションメッセージを取得します。
     */
    public function messages(): array
    {
        return [
            'book.unique' => 'この書籍にはすでにレビューを投稿しています。',
        ];
    }

    /**
     * バリデーション前に、ルートの書籍IDをリクエストに追加します。
     * 同じユーザーによる同一書籍へのレビューの重複チェックに使用します。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['book' => $this->route('book')->id]);
    }
}
