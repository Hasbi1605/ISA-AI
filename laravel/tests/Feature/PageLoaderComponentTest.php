<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageLoaderComponentTest extends TestCase
{
    public function test_page_loader_script_is_guarded_for_idempotent_rerenders(): void
    {
        $html = Blade::render('<x-page-loader />');

        $this->assertStringContainsString('window.__globalPageLoaderHandlers', $html);
        $this->assertStringContainsString('if (!window.__globalPageLoaderHandlers)', $html);
        $this->assertStringContainsString('document.addEventListener(\'livewire:navigating\', showLoader);', $html);
    }
}
