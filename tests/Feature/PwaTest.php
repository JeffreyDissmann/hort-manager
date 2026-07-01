<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_the_service_worker_is_served_from_the_root_with_root_scope(): void
    {
        // The built SW only exists after `npm run build`; fake it so the test is
        // independent of a frontend build (e.g. in CI).
        $path = public_path('build/sw.js');
        $created = ! file_exists($path);
        if ($created) {
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, "// test service worker\n");
        }

        try {
            $response = $this->get('/sw.js');

            $response->assertOk();
            $response->assertHeader('Service-Worker-Allowed', '/');
            $this->assertStringContainsString(
                'text/javascript',
                (string) $response->headers->get('Content-Type'),
            );
        } finally {
            if ($created) {
                @unlink($path);
            }
        }
    }
}
