<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->hotFile = public_path('hot');
    $this->originalHotFile = File::exists($this->hotFile) ? File::get($this->hotFile) : null;

    File::delete($this->hotFile);

    $this->contentSecurityPolicy = fn (): string => (string) $this->get(route('login'))
        ->assertOk()
        ->baseResponse
        ->headers
        ->get('Content-Security-Policy');
});

afterEach(function () {
    if ($this->originalHotFile !== null) {
        File::put($this->hotFile, $this->originalHotFile);
    } else {
        File::delete($this->hotFile);
    }
});

test('security headers are applied to web responses', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-XSS-Protection', '1; mode=block')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
});

test('a local vite dev server is allowed by the content security policy', function () {
    File::put($this->hotFile, 'http://localhost:5173');

    expect(($this->contentSecurityPolicy)())->toContain('localhost:5173');
});

test('an external vite host is never allowed by the content security policy', function () {
    File::put($this->hotFile, 'http://evil.example.com:5173');

    expect(($this->contentSecurityPolicy)())
        ->toContain('default-src')
        ->not->toContain('evil.example.com');
});
