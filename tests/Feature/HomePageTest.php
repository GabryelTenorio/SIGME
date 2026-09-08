<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_redirects_guests_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }
}
