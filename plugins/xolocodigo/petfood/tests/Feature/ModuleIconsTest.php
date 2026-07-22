<?php

use Webkul\Support\Enums\NavigationGroup;

it('registers the petfood module icon set as brand-blue line glyphs', function () {
    $icons = ['ausencias', 'calidad', 'complementos', 'compras', 'configuracion',
        'crm', 'empleados', 'fabricacion', 'facturas', 'inventario',
        'mantenimiento', 'rh', 'ventas'];

    foreach ($icons as $icon) {
        expect(svg("petfood-{$icon}")->toHtml())
            ->toContain('stroke="#2563eb"')
            ->toContain('viewBox=');
    }
});

it('serves petfood-branded art for the icons the navigation-group enum renders', function () {
    // Upstream's NavigationGroup enum (HasIcon) drives the module nav-group icons:
    // getIcon() returns "icon-{name}" -> resources/svg/{name}.svg (default blade set),
    // bypassing our AdminPanelProvider ->icon('petfood-*'). Our branding must live in
    // resources/svg or the sync's generic art shows instead (regression 2026-07-22).
    $brandedModules = [
        NavigationGroup::Contact, NavigationGroup::Sale, NavigationGroup::Purchase,
        NavigationGroup::Maintenance, NavigationGroup::Manufacturing, NavigationGroup::Inventory,
        NavigationGroup::Invoice, NavigationGroup::Employee, NavigationGroup::TimeOff,
        NavigationGroup::Recruitment, NavigationGroup::Plugin, NavigationGroup::Setting,
    ];

    foreach ($brandedModules as $group) {
        expect(svg($group->getIcon())->toHtml())
            ->toContain('stroke="#2563eb"');
    }
});
