<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationPagesTest extends TestCase
{
    public function test_login_and_php_page_urls_render_blade_views(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Login — JMACX LED Inventory');

        foreach ([
            'admin',
            'dashboard',
            'deployment-detail',
            'deployments',
            'index',
            'orders',
            'profile',
            'repair-detail',
            'repairs',
            'scanner',
            'settings',
            'shop',
            'stocks',
        ] as $page) {
            $this->get('/'.$page.'.php')->assertOk();
            $this->get('/'.$page.'.html')->assertRedirect('/'.$page.'.php');
        }
    }

    public function test_api_requests_are_dispatched_through_laravel(): void
    {
        $this->getJson('/api/not-a-resource')
            ->assertNotFound()
            ->assertJsonPath('message', 'API endpoint not found.');
    }

    public function test_api_preflight_requests_are_handled_without_running_legacy_dispatch(): void
    {
        $this->options('/api/auth/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, DELETE');
    }

}
