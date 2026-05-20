<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Define the parameters to pass to the migrate:fresh command.
     *
     * @return array
     */
    protected function migrateFreshUsing()
    {
        return [
            '--path' => [
                'database/migrations/central',
                'database/migrations',
                'database/migrations/tenant',
            ],
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
        ];
    }
}
