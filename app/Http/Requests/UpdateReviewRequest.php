<?php

namespace App\Http\Requests;

class UpdateReviewRequest extends StoreReviewRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('review')->user_id;
    }
}
