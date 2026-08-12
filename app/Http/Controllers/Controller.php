<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Resolve the number of items per page from the request query string,
     * clamped to a sane range. Defaults to 10, max 100, min 5.
     */
    protected function perPage(int $default = 10, int $max = 100): int
    {
        $requested = (int) request()->query('per_page', $default);

        return min(max($requested, 5), $max);
    }
}
