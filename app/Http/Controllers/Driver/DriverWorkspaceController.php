<?php

namespace App\Http\Controllers\Driver;

use App\Domain\Shipping\LastMile\DriverWorkspaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class DriverWorkspaceController extends Controller
{
    public function index(Request $request, DriverWorkspaceService $workspaces)
    {
        abort_unless($request->user(), 401);
        $profiles = $workspaces->availableFor($request->user());
        if ($profiles->count() === 1) {
            $request->session()->put((string) config('zigo_driver.context_session_key'), $profiles->first()->uuid);
            return redirect()->route('driver.dashboard');
        }
        return view('tenant.driver.workspaces', ['tenant' => null, 'profiles' => $profiles, 'centralDriver' => true]);
    }

    public function select(Request $request, DriverWorkspaceService $workspaces)
    {
        abort_unless($request->user(), 401);
        $data = $request->validate(['profile' => ['required', 'uuid']]);
        $profile = $workspaces->resolve($request->user(), $data['profile']);
        abort_unless($profile, 404);
        $request->session()->put((string) config('zigo_driver.context_session_key'), $profile->uuid);
        return redirect()->route('driver.dashboard');
    }
}
