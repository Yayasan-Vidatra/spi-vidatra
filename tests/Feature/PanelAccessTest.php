<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    public function test_beranda_renders_the_inertia_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Beranda'));
    }

    public function test_guest_is_redirected_from_panel_to_login(): void
    {
        $response = $this->get('/panel');

        $response->assertRedirect('/panel/login');
    }

    public function test_panel_login_page_is_accessible(): void
    {
        $response = $this->get('/panel/login');

        $response->assertOk();
    }
}
