@extends('admin.layouts.app')

@section('title', __('admin.provider_categories.subcategories_title', ['category' => $category->name_en]))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.provider-categories.index') }}" class="btn btn-sm btn-soft-secondary">
            <i class="ri-arrow-left-line align-middle"></i> {{ __('admin.common.back') }}
        </a>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ __('admin.provider_categories.subcategories_title', ['category' => $category->name_en]) }}</span>
            @can('provider-categories.create')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSubcategoryModal">
                    {{ __('admin.provider_categories.new_subcategory') }}
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
                        @forelse ($subcategories as $subcategory)
                            <tr>
                                <td><code>{{ $subcategory->slug }}</code></td>
                                <td>{{ $subcategory->name_en }}</td>
                                <td>{{ $subcategory->name_ar }}</td>
                                <td>{{ $subcategory->display_order }}</td>
                                <td>{{ $subcategory->providers_count }}</td>
                                <td class="text-end">
                                    @can('provider-categories.update')
                                        <button type="button" class="btn btn-sm btn-soft-primary rounded-circle" style="width: 32px; height: 32px;"
                                            data-bs-toggle="modal" data-bs-target="#editSubcategoryModal{{ $subcategory->id }}"
                                            title="{{ __('admin.common.edit') }}">
                                            <i class="ri-pencil-line align-middle"></i>
                                        </button>
                                    @endcan
                                    @can('provider-categories.delete')
                                        <form method="POST" action="{{ route('admin.provider-categories.subcategories.destroy', [$category, $subcategory]) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.provider_categories.confirm_delete_subcategory') }}');">
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
        <div class="modal fade" id="createSubcategoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.provider-categories.subcategories.store', $category) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.provider_categories.new_subcategory') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create-subcategory-slug" class="form-label">{{ __('admin.provider_categories.slug') }}</label>
                            <input type="text" name="slug" id="create-subcategory-slug" class="form-control" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label for="create-subcategory-name-en" class="form-label">{{ __('admin.provider_categories.name_en') }}</label>
                            <input type="text" name="name_en" id="create-subcategory-name-en" class="form-control" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label for="create-subcategory-name-ar" class="form-label">{{ __('admin.provider_categories.name_ar') }}</label>
                            <input type="text" name="name_ar" id="create-subcategory-name-ar" class="form-control" maxlength="255" required dir="rtl">
                        </div>
                        <div class="mb-3">
                            <label for="create-subcategory-display-order" class="form-label">{{ __('admin.provider_categories.display_order') }}</label>
                            <input type="number" name="display_order" id="create-subcategory-display-order" class="form-control" min="0" value="0">
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
        @foreach ($subcategories as $subcategory)
            <div class="modal fade" id="editSubcategoryModal{{ $subcategory->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('admin.provider-categories.subcategories.update', [$category, $subcategory]) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('admin.common.edit') }} — {{ $subcategory->name_en }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit-subcategory-slug-{{ $subcategory->id }}" class="form-label">{{ __('admin.provider_categories.slug') }}</label>
                                <input type="text" name="slug" id="edit-subcategory-slug-{{ $subcategory->id }}" class="form-control" maxlength="255" value="{{ $subcategory->slug }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-subcategory-name-en-{{ $subcategory->id }}" class="form-label">{{ __('admin.provider_categories.name_en') }}</label>
                                <input type="text" name="name_en" id="edit-subcategory-name-en-{{ $subcategory->id }}" class="form-control" maxlength="255" value="{{ $subcategory->name_en }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-subcategory-name-ar-{{ $subcategory->id }}" class="form-label">{{ __('admin.provider_categories.name_ar') }}</label>
                                <input type="text" name="name_ar" id="edit-subcategory-name-ar-{{ $subcategory->id }}" class="form-control" maxlength="255" value="{{ $subcategory->name_ar }}" required dir="rtl">
                            </div>
                            <div class="mb-3">
                                <label for="edit-subcategory-display-order-{{ $subcategory->id }}" class="form-label">{{ __('admin.provider_categories.display_order') }}</label>
                                <input type="number" name="display_order" id="edit-subcategory-display-order-{{ $subcategory->id }}" class="form-control" min="0" value="{{ $subcategory->display_order }}">
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
