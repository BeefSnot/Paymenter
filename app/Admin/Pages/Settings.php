<?php

namespace App\Admin\Pages;

use App\Classes\FilamentInput;
use App\Classes\Settings as ClassesSettings;
use App\Models\Setting;
use App\Providers\SettingsProvider;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Configuration';
    protected static ?string $title = 'Settings';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'admin.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting_values = [];
        foreach (\App\Classes\Settings::settings() as $group => $settings) {
            foreach ($settings as $setting) {
                $setting_values[$setting['name']] = Setting::get($setting['name'], $setting['default']);
            }
        }
        $this->data = $setting_values;
    }

    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Settings')
                ->tabs([
                    Tabs\Tab::make('General')
                        ->schema([
                            FilamentInput::make('timezone')
                                ->label('Timezone')
                                ->options(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL))
                                ->required(),
                            FilamentInput::make('app_language')
                                ->label('App Language')
                                ->options(glob(base_path('lang/*'), GLOB_ONLYDIR) ? array_map('basename', glob(base_path('lang/*'), GLOB_ONLYDIR)) : ['en'])
                                ->required(),
                            FilamentInput::make('app_url')
                                ->label('App URL')
                                ->url()
                                ->required(),
                            FilamentInput::make('theme')
                                ->label('Theme')
                                ->options(array_map('basename', glob(base_path('themes/*'), GLOB_ONLYDIR)))
                                ->required(),
                            FilamentInput::make('logo')
                                ->label('Logo')
                                ->file()
                                ->accept(['image/*']),
                            FilamentInput::make('footer_credits')
                                ->label('Footer Credits')
                                ->text()
                                ->default('© Your Company Name'),
                        ]),
                    Tabs\Tab::make('Cronjob')
                        ->schema([
                            FilamentInput::make('cronjob_invoice')
                                ->label('Send invoice if due date is x days away')
                                ->numeric()
                                ->required(),
                            FilamentInput::make('cronjob_invoice_reminder')
                                ->label('Send invoice reminder if due date is x days away')
                                ->numeric()
                                ->required(),
                            FilamentInput::make('cronjob_cancel')
                                ->label('Cancel order if pending for x days')
                                ->numeric()
                                ->required(),
                            FilamentInput::make('queue_worker_enabled')
                                ->label('Enable Queue Worker')
                                ->checkbox()
                                ->default(false),
                        ]),
                    // Other tabs...
                ]),
        ];
    }

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully.')
            ->success()
            ->send();
    }

    public static function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = auth()->user();

        return $user && $user->hasPermission('admin.settings.view');
    }
}
