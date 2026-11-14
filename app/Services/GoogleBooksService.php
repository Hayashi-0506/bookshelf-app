<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GoogleBooksService
{
    private const ENDPOINT = 'https://www.googleapis.com/books/v1/volumes';

    /**
     * ISBN で書籍情報を検索します。
     *
     * 見つからない場合は null を返します。
     */
    public function findByIsbn(string $isbn): ?array
    {
        // isbn: 指定で見つからない場合は、ISBN だけでキーワード検索する
        $volume = $this->findMatchingVolume($this->search("isbn:{$isbn}"), $isbn)
            ?? $this->findMatchingVolume($this->search($isbn), $isbn);

        if ($volume === null) {
            return null;
        }

        $info = $volume['volumeInfo'];

        return [
            'title' => $info['title'] ?? null,
            'author' => implode('、', $info['authors'] ?? []) ?: null,
            'published_date' => $info['publishedDate'] ?? null,
            'description' => $info['description'] ?? null,
            'image_url' => isset($info['imageLinks']['thumbnail'])
                ? str_replace('http://', 'https://', $info['imageLinks']['thumbnail'])
                : null,
        ];
    }

    /**
     * Google Books API にリクエストを送信します。
     */
    private function search(string $query): Response
    {
        return Http::timeout(5)
            ->retry(2, 300, throw: false)
            ->get(self::ENDPOINT, array_filter([
                'q' => $query,
                'key' => config('services.google_books.key'),
            ]))
            ->throw();
    }

    /**
     * 検索結果から、ISBN が完全に一致する書籍を取り出します。
     */
    private function findMatchingVolume(Response $response, string $isbn): ?array
    {
        return collect($response->json('items', []))->first(
            fn (array $item) => collect($item['volumeInfo']['industryIdentifiers'] ?? [])
                ->contains('identifier', $isbn)
        );
    }
}
