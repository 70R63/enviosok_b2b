<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Legacy tests that call actingAs(sysadmin) model an already completed
     * Network authentication. Password/2FA flow tests intentionally avoid
     * this helper until both factors have been exercised.
     */
    public function actingAs($user, $guard = null)
    {
        parent::actingAs($user, $guard);

        try {
            if (method_exists($user, 'hasRol') && $user->hasRol('sysadmin')) {
                $this->withSession([
                    'network.2fa_user_id' => $user->getAuthIdentifier(),
                    'network.2fa_verified_at' => now()->timestamp,
                ]);
            }
        } catch (\Throwable) {
            // Some isolated unit schemas intentionally omit role tables.
        }

        return $this;
    }
}
