<?php

it('registers the petfood module icon set as monochrome currentColor glyphs', function () {
    $icons = ['ausencias', 'calidad', 'complementos', 'compras', 'configuracion',
        'crm', 'empleados', 'fabricacion', 'facturas', 'inventario',
        'mantenimiento', 'rh', 'ventas'];

    foreach ($icons as $icon) {
        expect(svg("petfood-{$icon}")->toHtml())
            ->toContain('currentColor')
            ->toContain('viewBox="0 0 24 24"');
    }
});
