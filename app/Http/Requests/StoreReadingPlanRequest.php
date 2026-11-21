<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
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
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                Rule::unique('reading_plans', 'book_id')
                    ->where('user_id', $this->user()->id)
                    ->whereIn('status', [
                        ReadingPlanStatus::InProgress->value,
                        ReadingPlanStatus::Expired->value,
                    ]),
            ],
            'target_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * バリデーションメッセージを取得します。
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。',
            'book_id.exists' => '選択された書籍が存在しません。',
            'book_id.unique' => 'この書籍にはすでに未読了の読書計画があります。期日を延ばす場合は、既存の計画を編集してください。',
            'target_date.required' => '期日を入力してください。',
            'target_date.date_format' => '期日は「YYYY-MM-DD」の形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を入力してください。',
        ];
    }
}
