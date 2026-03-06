<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @mixin \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
 * @mixin \Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase
 * @mixin \Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication
 */
abstract class TestCase extends BaseTestCase
{
    //
}
