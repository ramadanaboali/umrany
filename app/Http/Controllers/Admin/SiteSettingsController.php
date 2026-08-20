<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Services\Admin\SiteSettingsService;

final class SiteSettingsController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', ['setting' => $this->settings->current()]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $setting = $this->settings->current();

        $this->settings->update($setting, [
            'site_name' => $request->string('site_name')->value(),
            'site_title' => $request->string('site_title')->value() ?: null,
            'contact_email' => $request->string('contact_email')->value() ?: null,
            'contact_phone' => $request->string('contact_phone')->value() ?: null,
            'contact_address' => $request->string('contact_address')->value() ?: null,
            'social_links' => array_filter($request->input('social_links', [])),
        ]);

        if ($request->hasFile('logo')) {
            $this->settings->updateLogo($setting, $request->file('logo'));
        }

        return back()->with('status', __('admin.flash.settings_updated'));
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->settings->removeLogo($this->settings->current());

        return back()->with('status', __('admin.flash.settings_logo_removed'));
    }
}
