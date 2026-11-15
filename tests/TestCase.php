<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * 各テストの前処理を行います。
     *
     * ビルド済みのアセット（public/build）がなくても画面を表示できるよう、Vite を無効にします。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
