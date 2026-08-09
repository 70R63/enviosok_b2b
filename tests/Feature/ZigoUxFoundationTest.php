<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class ZigoUxFoundationTest extends TestCase
{
    public function test_design_system_asset_and_component_foundation_exist(): void
    {
        $css = file_get_contents(public_path('css/zigo-design-system.css'));
        $this->assertStringContainsString('--z-navy-950:', $css);
        $this->assertStringContainsString('--z-focus:', $css);
        $this->assertStringContainsString('@media(min-width:640px)', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);

        foreach (['button','icon-button','card','badge','alert','input','select','textarea','choice','file-uploader','table','pagination','tabs','dialog','empty-state','loading','skeleton','metric','timeline','action-sheet','navigation','page-header','breadcrumbs'] as $component) {
            $this->assertFileExists(resource_path("views/components/zigo/{$component}.blade.php"));
        }
    }

    public function test_driver_shell_keeps_zigo_ownership_and_tenant_context_copy(): void
    {
        $layout = file_get_contents(resource_path('views/tenant/driver/layout.blade.php'));
        $dashboard = file_get_contents(resource_path('views/tenant/driver/dashboard.blade.php'));
        $this->assertStringContainsString('ZIGO DRIVER', $layout);
        $this->assertStringContainsString('PRODUCTO ZIGO', $layout);
        $this->assertStringNotContainsString("brand_name??\$tenant->name }} · DRIVER", $layout);
        $this->assertStringContainsString('Operando para', $dashboard);
        foreach (['Inicio','Entregas','Ganancias','Perfil'] as $destination) {
            $this->assertStringContainsString($destination, $layout);
        }
    }

    public function test_configuration_uses_human_receiver_policy_labels_and_keeps_enum_values(): void
    {
        $view = file_get_contents(resource_path('views/tenant/admin/delivery-proof-options.blade.php'));
        $this->assertStringContainsString('Cualquier persona en el domicilio', $view);
        $this->assertStringContainsString('value="{{ $policy }}"', $view);
        $this->assertStringContainsString('name="receiver_policy"', $view);
        $this->assertStringContainsString('z-form-grid', $view);
    }

    public function test_core_components_render_accessible_markup(): void
    {
        $button = Blade::render('<x-zigo.button type="submit">Guardar</x-zigo.button>');
        $alert = Blade::render('<x-zigo.alert tone="danger">Error</x-zigo.alert>');
        $this->assertStringContainsString('type="submit"', $button);
        $this->assertStringContainsString('z-btn', $button);
        $this->assertStringContainsString('role="alert"', $alert);
    }
}
