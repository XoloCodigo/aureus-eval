<?php

namespace Webkul\Website\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class PluginSettings extends Cluster
{
    protected static ?string $slug = 'website/settings';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('website::filament/app.navigation.settings.label');
    }

    public static function getNavigationGroup(): string
    {
        return __('website::filament/admin/clusters/configurations.navigation.group');
    }
}
