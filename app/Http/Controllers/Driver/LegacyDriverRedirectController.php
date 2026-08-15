<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LegacyDriverRedirectController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): RedirectResponse
    {
        abort_if($request->getHost() === config('zigo_driver.host'), 404);

        $target = rtrim((string) config('zigo_driver.url'), '/').'/driver';
        if (is_string($path) && $path !== '') {
            $target .= '/'.ltrim($path, '/');
        }

        return redirect()->away($target);
    }
}
