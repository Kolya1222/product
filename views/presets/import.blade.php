@extends('products::layouts.app')

@section('icon', 'upload')
@section('title', $title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('presets.module.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> К списку пресетов
        </a>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('presets.module.import.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label>Выберите товар</label>
            <select name="product_id" class="form-control" required>
                <option value="">-- Выберите товар --</option>
                @foreach($resources as $resource)
                    <option value="{{ $resource->id }}">{{ $resource->pagetitle }} (ID: {{ $resource->id }})</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>CSV-файл</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            <small class="text-muted">
                Формат: первая строка — коды атрибутов (например, <code>price,processor,memory</code>), 
                каждая следующая строка — значения для одного варианта.
            </small>
        </div>

        <button type="submit" class="btn btn-primary">Загрузить и создать варианты</button>
    </form>

    <hr>
    <h4>Пример CSV-файла</h4>
    <pre>price,processor,memory,name
349990,Apple M3 Max (16 ядер),48 ГБ,Полная комплектация
249990,Apple M3 Max (16 ядер),32 ГБ,Базовая комплектация</pre>
    <small class="text-muted">Коды атрибутов должны совпадать с созданными в системе атрибутами.</small>
@endsection
