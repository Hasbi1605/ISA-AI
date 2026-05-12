<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageLoaderComponentTest extends TestCase
{
    public function test_page_loader_script_is_guarded_for_idempotent_rerenders(): void
    {
        $html = Blade::render('<x-page-loader />');
        $componentSource = file_get_contents(resource_path('views/components/page-loader.blade.php'));
        $this->assertIsString($componentSource);

        $this->assertStringContainsString('window.__globalPageLoaderHandlers', $html);
        $this->assertStringContainsString('if (!window.__globalPageLoaderHandlers)', $html);
        $this->assertStringContainsString('document.addEventListener(\'livewire:navigating\', showLoader);', $html);
        $this->assertStringContainsString('window.__suppressGlobalPageLoaderOnce === true', $html);
        $this->assertStringContainsString('ista.globalPageLoader.suppressOnce', $componentSource);
        $this->assertStringContainsString('suppress-global-page-loader', $componentSource);
        $this->assertStringContainsString('window.sessionStorage.removeItem(suppressStorageKey)', $componentSource);
    }
}
