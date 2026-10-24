<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->when(
                $request->routeIs('books.show'),
                $this->description,
            ),
            'image_url' => $this->image_url,
            'user' => new UserResource($this->whenLoaded('user')),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'reviews_count' => $this->whenCounted('reviews'),
            'reviews_avg_rating' => $this->whenAggregated('reviews', 'rating', 'avg',
                fn ($value) => $value !== null ? round($value, 1) : null
            ),
        ];
    }
}
