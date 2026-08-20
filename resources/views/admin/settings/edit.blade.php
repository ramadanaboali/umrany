@extends('admin.layouts.app')

@section('title', __('admin.settings.title'))

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="site_name" class="form-label">{{ __('admin.settings.site_name') }}</label>
                            <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $setting->site_name) }}" required
                                class="form-control @error('site_name') is-invalid @enderror">
                            @error('site_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="site_title" class="form-label">{{ __('admin.settings.site_title') }}</label>
                            <input type="text" id="site_title" name="site_title" value="{{ old('site_title', $setting->site_title) }}"
                                class="form-control @error('site_title') is-invalid @enderror">
                            <div class="form-text">{{ __('admin.settings.site_title_hint') }}</div>
                            @error('site_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="card border">
                            <div class="card-header py-2">
                                <h6 class="mb-0">{{ __('admin.settings.contacts') }}</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">{{ __('admin.settings.contact_email') }}</label>
                                    <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $setting->contact_email) }}"
                                        class="form-control @error('contact_email') is-invalid @enderror">
                                    @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">{{ __('admin.settings.contact_phone') }}</label>
                                    <input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $setting->contact_phone) }}"
                                        class="form-control @error('contact_phone') is-invalid @enderror">
                                    @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-0">
                                    <label for="contact_address" class="form-label">{{ __('admin.settings.contact_address') }}</label>
                                    <input type="text" id="contact_address" name="contact_address" value="{{ old('contact_address', $setting->contact_address) }}"
                                        class="form-control @error('contact_address') is-invalid @enderror">
                                    @error('contact_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="card border mt-3">
                            <div class="card-header py-2">
                                <h6 class="mb-0">{{ __('admin.settings.social_links') }}</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    @foreach (\Modules\Core\Enums\SocialPlatform::cases() as $platform)
                                        <div class="col-sm-6">
                                            <label for="social-{{ $platform->value }}" class="form-label text-capitalize">{{ $platform->value }}</label>
                                            <input type="url" id="social-{{ $platform->value }}" name="social_links[{{ $platform->value }}]"
                                                value="{{ old('social_links.'.$platform->value, $setting->social_links[$platform->value] ?? '') }}"
                                                placeholder="https://…"
                                                class="form-control @error('social_links') is-invalid @enderror">
                                        </div>
                                    @endforeach
                                    @error('social_links')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">{{ __('admin.common.save') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">{{ __('admin.settings.logo') }}</h6>
                </div>
                <div class="card-body text-center">
                    @if ($setting->logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($setting->logo_path) }}"
                            alt="{{ __('admin.settings.logo') }}" class="img-fluid mb-3" style="max-height: 120px;">
                    @else
                        <p class="text-muted">{{ __('admin.settings.no_logo') }}</p>
                    @endif

                    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mb-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="site_name" value="{{ $setting->site_name }}">
                        <div class="mb-2">
                            <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-soft-primary btn-sm w-100">{{ __('admin.settings.upload_logo') }}</button>
                    </form>

                    @if ($setting->logo_path)
                        <form method="POST" action="{{ route('admin.settings.logo.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger btn-sm w-100">{{ __('admin.settings.remove_logo') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
