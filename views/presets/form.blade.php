@extends('products::layouts.app')

@section('icon', 'magic')
@section('title', $title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('presets.module.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> К списку
        </a>
    </div>
@endsection

@section('content')
    <form action="{{ isset($preset) ? route('presets.module.update', $preset->id) : route('presets.module.store') }}"
        method="POST">
        @csrf
        <div class="form-group">
            <label>Название</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $preset->name ?? '') }}" required>
        </div>
        <div class="form-group">
            <label>Описание</label>
            <textarea name="description" class="form-control">{{ old('description', $preset->description ?? '') }}</textarea>
        </div>

        <h4>Атрибуты</h4>
        <div id="attributes-container">
            @php
                $oldAttrs = old('attributes', isset($preset) ? $preset->attributes->toArray() : []);
            @endphp
            @foreach ($oldAttrs as $index => $attr)
                <div class="attr-row" style="margin-bottom:5px;">
                    <select name="attributes[{{ $index }}][attribute_id]" class="form-control attr-select"
                        style="width:250px; display:inline-block;">
                        <option value="">-- Выберите атрибут --</option>
                        @foreach ($allAttributes as $a)
                            <option value="{{ $a->id }}" @if ((int) $a->id == (int) $attr['attribute_id']) selected @endif>
                                {{ $a->name }} ({{ $a->code }})
                            </option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-xs btn-danger remove-attr" style="margin-left:5px;">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            @endforeach
        </div>
        <div style="margin-top:5px;">
            <button type="button" id="add-attr" class="btn btn-sm btn-secondary">+ Добавить атрибут</button>
            <button type="button" id="create-attribute-btn" class="btn btn-sm btn-success"><i class="fa fa-plus"></i>
                Создать атрибут</button>
            <button type="button" id="manage-categories-btn" class="btn btn-sm btn-warning"><i class="fa fa-folder"></i>
                Категории</button>
        </div>
        <hr>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
@endsection

@push('modals')
    <x-products::modals.attribute-form id="addAttributeModal" title="Новый атрибут" />
    <x-products::modals.categories id="manageCategoriesModal" />
@endpush

@push('scripts')
    <script src="{{ asset('/js/product-variants.js') }}"></script>
    <script>
        jQuery(document).ready(function() {
            PresetsForm.init({
                csrfToken: '{{ csrf_token() }}',
                allAttributes: @json($allAttributes),
                attrCount: {{ count($oldAttrs) }},
                urls: {
                    categoriesList: '/admin/product-variants/categories',
                    categoriesStore: '/admin/product-variants/categories',
                    categoriesUpdate: '/admin/product-variants/categories',
                    categoriesDelete: '/admin/product-variants/categories',
                    attributesStore: '/admin/product-variants/attributes',
                    attributesTypes: '/admin/product-variants/attributes/types'
                }
            });
        });
    </script>
@endpush
