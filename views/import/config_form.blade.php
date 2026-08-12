@extends('products::layouts.app')

@section('icon', 'cogs')
@section('title', $title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('presets.module.import') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Назад к импорту
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="alert alert-info">
        <b>Как настроить профиль:</b><br>
        1. В поле "Источник" введите точное название колонки из первой строки вашего файла (Excel или CSV).<br>
        2. В поле "Куда записывать" выберите системное поле или характеристику товара.<br>
        3. Отметьте галочкой "Уник. ключ" поле, по которому товары будут искаться в базе (обычно это Артикул или Заголовок).
    </div>

    <form action="{{ isset($config) ? route('presets.module.import.config.update', $config->id) : route('presets.module.import.config.store') }}" method="POST">
        @csrf
        @if(isset($config)) <input type="hidden" name="_method" value="PUT"> @endif

        <div class="panel panel-default">
            <div class="panel-heading"><b>Основные настройки</b></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Название профиля</label>
                            <input type="text" name="name" class="form-control" value="{{ $config->name ?? '' }}" placeholder="Например: Прайс поставщика X" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Тип файла</label>
                            <select name="source_type" class="form-control">
                                @php $st = $config->source_type ?? 'csv'; @endphp
                                <option value="csv" {{ $st == 'csv' ? 'selected' : '' }}>CSV</option>
                                <option value="xlsx" {{ $st == 'xlsx' ? 'selected' : '' }}>XLSX</option>
                                <option value="xml" {{ $st == 'xml' ? 'selected' : '' }}>XML</option>
                                <option value="json" {{ $st == 'json' ? 'selected' : '' }}>JSON</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Режим синхронизации</label>
                            <select name="sync_mode" class="form-control">
                                @php $sm = $config->sync_mode ?? 'incremental'; @endphp
                                <option value="incremental" {{ $sm == 'incremental' ? 'selected' : '' }}>Только добавлять/обновлять</option>
                                <option value="deactivate" {{ $sm == 'deactivate' ? 'selected' : '' }}>Деактивировать отсутствующие</option>
                                <option value="full" {{ $sm == 'full' ? 'selected' : '' }}>Удалять отсутствующих</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check" style="margin-top: 35px;">
                            @php $ca = $config->create_attrs ?? 1; @endphp
                            <input type="checkbox" name="create_attrs" value="1" {{ $ca ? 'checked' : '' }}>
                            <label>Создавать новые характеристики</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Категория по умолчанию (ID)</label>
                            <input type="number" name="default_parent_id" class="form-control" value="{{ $config->mapping['default_parent'] ?? 0 }}" placeholder="Напр: 7">
                            <small>Если в файле нет колонки с категорией, все товары уйдут сюда.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><b>Связь колонок файла и базы данных</b></div>
            <div class="panel-body">
                <table class="table table-bordered" id="mapTable">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Колонка в файле (точное название)</th>
                            <th style="width: 40%;">Куда записывать</th>
                            <th style="width: 10%;" class="text-center">Уник. ключ</th>
                            <th style="width: 10%;"></th>
                        </tr>
                    </thead>
                    <tbody id="mapBody"></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-success" onclick="addMappingRow()">
                    <i class="fa fa-plus"></i> Добавить связь
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i> Сохранить профиль
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    const existingCodes = @json($attributes->pluck('code')->toArray());

    function addMappingRow(src = '', tgt = '', isUnique = false) {
        let tr = document.createElement('tr');
        
        let selectVal = tgt;
        let customVal = '';
        let isCustom = false;

        if (tgt.startsWith('general:') && !existingCodes.includes(tgt.replace('general:', ''))) {
            selectVal = 'custom_general';
            customVal = tgt.replace('general:', '');
            isCustom = true;
        } else if (tgt.startsWith('variant:') && !existingCodes.includes(tgt.replace('variant:', ''))) {
            selectVal = 'custom_variant';
            customVal = tgt.replace('variant:', '');
            isCustom = true;
        }

        tr.innerHTML = `
            <td><input type="text" name="source[]" class="form-control" value="${src}" placeholder="Напр: Цена или color"></td>
            <td>
                <input type="hidden" name="target[]" class="target-hidden" value="${tgt}">
                <select class="form-control target-select" onchange="handleTargetChange(this)">
                    <option value="">-- Пропустить --</option>
                    <optgroup label="Системные поля товара">
                        <option value="pagetitle" ${tgt === 'pagetitle' ? 'selected' : ''}>Заголовок (Название)</option>
                        <option value="parent" ${tgt === 'parent' ? 'selected' : ''}>ID категории</option>
                        <option value="template" ${tgt === 'template' ? 'selected' : ''}>Шаблон</option>
                        <option value="published" ${tgt === 'published' ? 'selected' : ''}>Опубликован</option>
                        <option value="introtext" ${tgt === 'introtext' ? 'selected' : ''}>Аннотация</option>
                    </optgroup>
                    <optgroup label="Общие характеристики (для товара)">
                        @foreach($attributes as $attr)
                            <option value="general:{{ $attr->code }}" ${tgt === 'general:{{ $attr->code }}' ? 'selected' : ''}>{{ $attr->name }} ({{ $attr->code }})</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Характеристики вариантов (Торговые предложения)">
                        @foreach($attributes as $attr)
                            <option value="variant:{{ $attr->code }}" ${tgt === 'variant:{{ $attr->code }}' ? 'selected' : ''}>{{ $attr->name }} ({{ $attr->code }})</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="--- СОЗДАТЬ НОВЫЙ ---">
                        <option value="custom_general" ${selectVal === 'custom_general' ? 'selected' : ''}>Новый (Общая характеристика)</option>
                        <option value="custom_variant" ${selectVal === 'custom_variant' ? 'selected' : ''}>Новый (Характеристика варианта)</option>
                    </optgroup>
                </select>
                
                <div class="custom-attr-div" style="display:${isCustom ? 'block' : 'none'}; margin-top:5px;">
                    <input type="text" class="form-control target-custom" placeholder="Введите код (напр: price)" value="${customVal}" oninput="handleCustomInput(this)">
                </div>
            </td>
            <td class="text-center"><input type="radio" name="unique_key" value="${tgt}" ${isUnique ? 'checked' : ''} ${tgt ? '' : 'disabled'}></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()"><i class="fa fa-times"></i></button></td>
        `;
        document.getElementById('mapBody').appendChild(tr);
    }

    function handleTargetChange(selectEl) {
        let row = selectEl.closest('tr');
        let customDiv = row.querySelector('.custom-attr-div');
        let hiddenInput = row.querySelector('.target-hidden');
        let radio = row.querySelector('input[type="radio"]');
        
        let val = selectEl.value;
        
        if (val === 'custom_general' || val === 'custom_variant') {
            customDiv.style.display = 'block';
            let prefix = val === 'custom_general' ? 'general:' : 'variant:';
            let customInput = row.querySelector('.target-custom');
            hiddenInput.value = prefix + customInput.value;
        } else {
            customDiv.style.display = 'none';
            hiddenInput.value = val;
        }
        
        radio.value = hiddenInput.value;
        radio.disabled = (hiddenInput.value === '');
    }

    function handleCustomInput(inputEl) {
        let row = inputEl.closest('tr');
        let select = row.querySelector('.target-select');
        let hiddenInput = row.querySelector('.target-hidden');
        let radio = row.querySelector('input[type="radio"]');
        
        let prefix = select.value === 'custom_general' ? 'general:' : 'variant:';
        hiddenInput.value = prefix + inputEl.value;
        
        radio.value = hiddenInput.value;
        radio.disabled = (hiddenInput.value === '');
    }

    function updateRadio(input) {
        let radio = input.closest('tr').querySelector('input[type="radio"]');
        radio.value = input.value;
        radio.disabled = (input.value === '');
    }

    jQuery(document).ready(function($) {
        @if(isset($config) && !empty($config->mapping))
            @foreach($config->mapping as $src => $tgt)
                @if($src !== 'unique_key' && $src !== 'default_parent')
                    addMappingRow('{{ $src }}', '{{ $tgt }}', '{{ $config->mapping['unique_key'] ?? '' }}' === '{{ $tgt }}');
                @endif
            @endforeach
        @else
            addMappingRow();
        @endif
    });
</script>
@endpush