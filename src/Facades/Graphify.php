<?php

namespace Jansamnan\Graphify\Facades;

use Illuminate\Support\Facades\Facade;

class Graphify extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'graphify';
    }
}
