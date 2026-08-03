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
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('presets.module.massAssign.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label>Выберите пресет</label>
            <select name="preset_id" class="form-control" required>
                <option value="">-- Выберите пресет --</option>
                @foreach ($presets as $preset)
                    <option value="{{ $preset->id }}">{{ $preset->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Режим</label>
            <select name="mode" class="form-control">
                <option value="replace">Заменить существующие атрибуты</option>
                <option value="add">Добавить к существующим</option>
            </select>
        </div>

        <div class="form-group">
            <label>Ресурсы</label>
            <div id="resource-tree" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">
            </div>
            <small class="text-muted">Отметьте папки и/или отдельные товары. Для папок можно включить опции ниже.</small>
        </div>

        <div class="form-check">
            <input type="checkbox" name="include_children" id="include_children" class="form-check-input">
            <label for="include_children" class="form-check-label">Включая дочерние ресурсы</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="only_children" id="only_children" class="form-check-input">
            <label for="only_children" class="form-check-label">Только дочерние ресурсы (сам выбранный не включать)</label>
        </div>
        <small class="text-muted">Если отмечены оба, приоритет имеет "Только дочерние".</small>

        <hr>
        <button type="submit" class="btn btn-primary">Применить пресет</button>
    </form>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/themes/default-dark/style.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/jstree.min.js"></script>

    <style>
        #resource-tree {
            background: #1e1e2f;
            padding: 16px;
            border: 1px solid #2d2d44;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .jstree-default-dark .jstree-anchor {
            font-size: 14px;
            font-weight: 500;
            color: #cdd6f4;
            transition: color 0.2s, background 0.2s;
            border-radius: 6px;
            padding: 4px 8px;
            margin: 2px 0;
        }

        .jstree-default-dark .jstree-anchor:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .jstree-default-dark .jstree-clicked {
            background: rgba(76, 154, 255, 0.2) !important;
            color: #ffffff !important;
        }

        .jstree-default-dark .jstree-checkbox {
            background-image: url("https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/themes/default-dark/32px.png");
        }

        .jstree-default-dark .jstree-ocl {
            background-image: url("https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/themes/default-dark/32px.png");
        }

        #resource-tree::-webkit-scrollbar {
            width: 6px;
        }

        #resource-tree::-webkit-scrollbar-track {
            background: #2d2d44;
            border-radius: 3px;
        }

        #resource-tree::-webkit-scrollbar-thumb {
            background: #57577a;
            border-radius: 3px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('#resource-tree').jstree({
                'core': {
                    'data': {
                        'url': '{{ route('presets.module.massAssign.children') }}',
                        'data': function(node) {
                            return {
                                'id': node.id === '#' ? 0 : node.id
                            };
                        }
                    },
                    'themes': {
                        'name': 'default-dark',
                        'responsive': true
                    }
                },
                'plugins': ['checkbox'],
                'checkbox': {
                    'three_state': false,
                    'cascade': 'undetermined'
                }
            });

            document.getElementById('mass-assign-form').addEventListener('submit', function(e) {
                var selectedIds = $('#resource-tree').jstree('get_checked', false);
                this.querySelectorAll('input[name="resource_ids[]"]').forEach(function(el) {
                    el.remove();
                });
                if (selectedIds.length) {
                    selectedIds.forEach(function(id) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'resource_ids[]';
                        input.value = id;
                        e.target.appendChild(input);
                    });
                }
            });

            var inc = document.getElementById('include_children');
            var only = document.getElementById('only_children');

            only.addEventListener('change', function() {
                if (this.checked) {
                    inc.checked = false;
                    inc.disabled = true;
                } else {
                    inc.disabled = false;
                }
            });

            inc.addEventListener('change', function() {
                if (this.checked) {
                    only.checked = false;
                    only.disabled = true;
                } else {
                    only.disabled = false;
                }
            });
        });
    </script>
@endpush
