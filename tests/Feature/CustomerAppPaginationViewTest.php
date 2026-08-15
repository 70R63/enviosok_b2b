<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CustomerAppPaginationViewTest extends TestCase
{
    public function test_first_page_has_next_keeps_other_paginator_query_and_renders_no_svg(): void
    {
        $paginator = $this->paginator('envios', 1, 30, ['estado' => 'activos', 'pendientes' => 2]);

        $html = (string) $paginator->links('tenant.customer.partials.pagination');

        $this->assertStringContainsString('Siguiente', $html);
        $this->assertStringContainsString('aria-disabled="true">Anterior', $html);
        $this->assertStringContainsString('estado=activos&amp;pendientes=2&amp;envios=2', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_second_page_has_previous_and_pending_page_name_is_preserved(): void
    {
        $paginator = $this->paginator('pendientes', 2, 20, ['estado' => 'todos', 'envios' => 2]);

        $html = (string) $paginator->links('tenant.customer.partials.pagination');

        $this->assertStringContainsString('rel="prev">Anterior', $html);
        $this->assertStringContainsString('aria-current="page">2', $html);
        $this->assertStringContainsString('estado=todos&amp;envios=2&amp;pendientes=1', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    private function paginator(string $pageName, int $page, int $total, array $query): LengthAwarePaginator
    {
        return (new LengthAwarePaginator(
            range(1, 10),
            $total,
            10,
            $page,
            ['path' => '/app/envios', 'pageName' => $pageName]
        ))->appends($query);
    }
}
