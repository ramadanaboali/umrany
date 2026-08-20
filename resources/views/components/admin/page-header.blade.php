@props(['title', 'breadcrumbs' => []])

{{-- Every admin screen opens with this instead of hand-rolling a title bar — see
     docs/architecture/admin-portal.md § Theme and assets. --}}
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">{{ $title }}</h4>
            <div class="page-title-right d-flex align-items-center gap-2">
                @if (count($breadcrumbs) > 0)
                    <ol class="breadcrumb m-0">
                        @foreach ($breadcrumbs as $label => $url)
                            @if (is_int($label))
                                <li class="breadcrumb-item active">{{ $url }}</li>
                            @else
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                            @endif
                        @endforeach
                    </ol>
                @endif
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        </div>
    </div>
</div>
