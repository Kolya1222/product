@extends('products::layouts.app')

@section('icon', 'magic')
@section('title', $title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('presets.module.create') }}" class="btn btn-primary">
            <i class="fa fa-plus"></i> Создать пресет
        </a>
        <a href="javascript:location.reload();" class="btn btn-secondary">
            <i class="fa fa-refresh"></i> Обновить
        </a>
        <a href="{{ route('presets.module.massAssign') }}" class="btn btn-warning">
            <i class="fa fa-download"></i> Массовое назначение
        </a>
        <a href="{{ route('presets.module.import') }}" class="btn btn-success">
            <i class="fa fa-upload"></i> Импорт вариантов
        </a>
    </div>
@endsection

@section('content')
    <table class="table">
        <thead>
            <tr>
                <th>Название</th>
                <th>Описание</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($presets as $preset)
                <tr>
                    <td>{{ $preset->name }}</td>
                    <td>{{ $preset->description }}</td>
                    <td>
                        <a href="{{ route('presets.module.edit', $preset->id) }}" class="btn btn-xs btn-info">
                            <i class="fa fa-pencil"></i> Ред.
                        </a>
                        <form action="{{ route('presets.module.destroy', $preset->id) }}" method="POST"
                            style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-danger"
                                onclick="return confirm('Удалить пресет?')">
                                <i class="fa fa-trash"></i> Удалить
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Пресетов пока нет.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
