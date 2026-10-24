<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BookCollection extends ResourceCollection
{
    /**
     * ページネーション情報から meta のページリンク一覧を削除します。
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        unset(
            $default['links'],
            $default['meta']['links'],
            $default['meta']['from'],
            $default['meta']['to'],
            $default['meta']['path']
        );

        return $default;
    }
}
