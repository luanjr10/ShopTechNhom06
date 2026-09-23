<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase mặc định wrap transaction trên MỌI connection khai báo trong
     * config/database.php, kể cả 'mongodb' — CLI test không có ext-mongodb nên việc
     * đó throw "MongoDB\Driver\Manager not found". Test chỉ cần transaction trên
     * connection sqlite (DB_CONNECTION trong phpunit.xml).
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = ['sqlite'];
}
