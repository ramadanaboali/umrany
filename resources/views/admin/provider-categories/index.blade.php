@extends('admin.layouts.app')

@section('title', __('admin.provider_categories.title'))

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ __('admin.provider_categories.title') }}</span>
            @can('provider-categories.create')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                    {{ __('admin.provider_categories.new') }}
                </button>
            @endcan
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.provider_categories.slug') }}</th>
                            <th>{{ __('admin.provider_categories.name_en') }}</th>
                            <th>{{ __('admin.provider_categories.name_ar') }}</th>
                            <th>{{ __('admin.provider_categories.display_order') }}</th>
                            <th>{{ __('admin.provider_categories.providers_count') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td><code>{{ $category->slug }}</code></td>
                                <td>{{ $category->name_en }}</td>
                                <td>{{ $category->name_ar }}</td>
                                <td>{{ $category->display_order }}</td>
                                <td>{{ $category->providers_count }}</td>
                                <td class="text-end">
                                    @can('provider-categories.view')
                                        <a href="{{ route('admin.provider-categories.subcategories.index', $category) }}"
                                            class="btn btn-sm btn-soft-secondary rounded-circle" style="width: 32px; height: 32px;"
                                            data-bs-toggle="tooltip" title="{{ __('admin.provider_categories.manage_subcategories') }}">
                                            <i class="ri-list-unordered align-middle"></i>
                                        </a>
                                    @endcan
                                    @can('provider-categories.update')
                                        <button type="button" class="btn btn-sm btn-soft-primary rounded-circle" style="width: 32px; height: 32px;"
                                            data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $category->id }}"
                                            title="{{ __('admin.common.edit') }}">
                                            <i class="ri-pencil-line align-middle"></i>
                                        </button>
                                    @endcan
                                    @can('provider-categories.delete')
                                        <form method="POST" action="{{ route('admin.provider-categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.provider_categories.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-soft-danger rounded-circle" style="width: 32px; height: 32px;" title="{{ __('admin.common.delete') }}">
                                                <i class="ri-delete-bin-line align-middle"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('admin.common.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('provider-categories.create')
        <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.provider-categories.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.provider_categories.new') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create-category-slug" class="form-label">{{ __('admin.provider_categories.slug') }}</label>
                            <input type="text" name="slug" id="create-category-slug" class="form-control" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label for="create-category-name-en" class="form-label">{{ __('admin.provider_categories.name_en') }}</label>
                            <input type="text" name="name_en" id="create-category-name-en" class="form-control" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label for="create-category-name-ar" class="form-label">{{ __('admin.provider_categories.name_ar') }}</label>
                            <input type="text" name="name_ar" id="create-category-name-ar" class="form-control" maxlength="255" required dir="rtl">
                        </div>
                        <div class="mb-3">
                            <label for="create-category-display-order" class="form-label">{{ __('admin.provider_categories.display_order') }}</label>
                            <input type="number" name="display_order" id="create-category-display-order" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('admin.common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('provider-categories.update')
        @foreach ($categories as $category)
            <div class="modal fade" id="editCategoryModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('admin.provider-categories.update', $category) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('admin.common.edit') }} — {{ $category->name_en }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit-category-slug-{{ $category->id }}" class="form-label">{{ __('admin.provider_categories.slug') }}</label>
                                <input type="text" name="slug" id="edit-category-slug-{{ $category->id }}" class="form-control" maxlength="255" value="{{ $category->slug }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-category-name-en-{{ $category->id }}" class="form-label">{{ __('admin.provider_categories.name_en') }}</label>
                                <input type="text" name="name_en" id="edit-category-name-en-{{ $category->id }}" class="form-control" maxlength="255" value="{{ $category->name_en }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-category-name-ar-{{ $category->id }}" class="form-label">{{ __('admin.provider_categories.name_ar') }}</label>
                                <input type="text" name="name_ar" id="edit-category-name-ar-{{ $category->id }}" class="form-control" maxlength="255" value="{{ $category->name_ar }}" required dir="rtl">
                            </div>
                            <div class="mb-3">
                                <label for="edit-category-display-order-{{ $category->id }}" class="form-label">{{ __('admin.provider_categories.display_order') }}</label>
                                <input type="number" name="display_order" id="edit-category-display-order-{{ $category->id }}" class="form-control" min="0" value="{{ $category->display_order }}">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('admin.common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
@endsection
