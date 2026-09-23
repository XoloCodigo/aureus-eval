<?php

use Webkul\Support\Enums\NavigationGroup;

/*
 * Our framed line icons carry a brand accent (#F5821F) that upstream's generic
 * art never uses, so its presence proves the nav renders OUR art.
 */

it('serves petfood art for the Calidad e Inocuidad group', function () {
    expect(svg('petfood-calidad')->toHtml())->toContain('#F5821F');
});

it('serves petfood-branded art for every icon the navigation-group enum renders', function () {
    // Upstream's NavigationGroup enum (HasIcon) drives the module nav-group icons:
    // getIcon() returns "icon-{name}" -> resources/svg/{name}.svg (default blade set),
    // bypassing our AdminPanelProvider. Our branding must live in resources/svg or a
    // sync's generic art shows instead (regression 2026-07-22).
    $groupsWithUpstreamArt = collect(NavigationGroup::cases())
        ->reject(fn (NavigationGroup $group): bool => str_contains(svg($group->getIcon())->toHtml(), '#F5821F'))
        ->map(fn (NavigationGroup $group): string => $group->name)
        ->values()
        ->all();

    expect($groupsWithUpstreamArt)->toBe([]);
});
