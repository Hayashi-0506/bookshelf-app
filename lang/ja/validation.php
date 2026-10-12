<?php

return [

    'required' => ':attributeを入力してください。',
    'email' => ':attributeは有効なメールアドレス形式で入力してください。',
    'max' => [
        'string' => ':attributeは:max文字以下で入力してください。',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'confirmed' => ':attributeと:attribute確認が一致しません。',
    'unique' => '指定の:attributeは既に使用されています。',
    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
    ],
];
