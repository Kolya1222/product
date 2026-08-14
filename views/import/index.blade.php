@extends('products::layouts.app')

@section('icon', 'upload')
@section('title', $title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('presets.module.import.config.create') }}" class="btn btn-success">
            <i class="fa fa-plus"></i> Создать профиль
        </a>
        <a href="{{ route('presets.module.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Назад
        </a>
    </div>
@endsection

@section('content')
    <div class="container-fluid">

        <div class="panel panel-default" style="margin-bottom: 20px;">
            <div class="panel-heading"><b>Профили маппинга</b></div>
            <div class="panel-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Название профиля</th>
                            <th>Тип файла</th>
                            <th>Режим</th>
                            <th width="150px">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($configs as $c)
                            <tr>
                                <td>{{ $c->name }}</td>
                                <td>{{ strtoupper($c->source_type) }}</td>
                                <td>
                                    @if($c->sync_mode == 'incremental') Добавлять/Обновлять
                                    @elseif($c->sync_mode == 'deactivate') Деактивировать
                                    @else Удалять @endif
                                </td>
                                <td>
                                    <a href="{{ route('presets.module.import.config.edit', $c->id) }}"
                                        class="btn btn-xs btn-info">
                                        <i class="fa fa-pencil"></i> Изменить
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Профилей пока нет. Нажмите "Создать профиль".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel panel-primary">
            <div class="panel-heading"><b>Запуск импорта</b></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Выберите профиль</label>
                            <select id="config_id" class="form-control">
                                <option value="">-- Авто-маппинг (без профиля) --</option>
                                @foreach($configs as $config)
                                    <option value="{{ $config->id }}">{{ $config->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Используйте "Авто-маппинг", если файл выгружен через модуль Экспорта.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Файл (CSV, XLSX до 2 ГБ)</label>
                            <input type="file" id="file_input" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Категория (для авто)</label>
                            <input type="number" id="auto_parent_id" class="form-control" placeholder="ID папки (напр: 2)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check" style="margin-top: 30px;">
                            <input type="checkbox" id="test_mode" class="form-check-input" value="1">
                            <label for="test_mode" class="form-check-label">Тестовый режим</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button id="startImportBtn" class="btn btn-primary btn-block" style="margin-top: 10px;">
                            <i class="fa fa-play"></i> Запустить импорт
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="importStatus" style="display:none; margin-top: 20px;">
            <h4 id="statusTitle">Загрузка файла на сервер...</h4>
            <div class="progress" style="height: 30px;">
                <div id="progressBar" class="progress-bar progress-bar-striped active" role="progressbar"
                    style="width: 0%;">0%</div>
            </div>

            <div class="row" style="margin-top: 20px;">
                <div class="col-md-4">
                    <div class="panel panel-info">
                        <div class="panel-heading">Статистика</div>
                        <div class="panel-body">
                            <p>Прочитано строк: <span id="statRows" class="badge">0</span></p>
                            <p>Обработано товаров: <span id="statProducts" class="badge">0</span></p>
                            <p>Ошибок: <span id="statErrors" class="badge badge-danger">0</span></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="panel panel-danger">
                        <div class="panel-heading">Лог ошибок</div>
                        <div class="panel-body" id="errorLog"
                            style="max-height: 300px; overflow-y: auto; font-size: 12px; font-family: monospace; background: #fff5f5;">
                            <p class="text-muted">Ожидание запуска...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        jQuery(document).ready(function ($) {
            let isRunning = false;
            let totalRows = 0, totalProducts = 0, totalErrors = 0;

            $('#startImportBtn').on('click', function () {
                if (isRunning) return;

                let configId = $('#config_id').val();
                let fileInput = document.getElementById('file_input');
                let isTest = $('#test_mode').is(':checked') ? 1 : 0;

                if (!fileInput.files.length) {
                    alert('Выберите файл для импорта!');
                    return;
                }

                isRunning = true;
                $('#importStatus').show();
                $('#errorLog').html('');
                $('#progressBar').css('width', '0%').text('0%').removeClass('progress-bar-danger').addClass('active');
                $('#startImportBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Идет процесс...');

                uploadFileInChunks(fileInput.files[0], function (finalPath) {
                    // Файл загружен, начинаем импорт
                    $('#statusTitle').text('Импорт и обработка данных...');
                    readChunk(finalPath, 2, configId, isTest);
                });
            });

            // --- ФАЗА 1: ЗАГРУЗКА ФАЙЛА ---
            function uploadFileInChunks(file, callback) {
                const chunkSize = 5 * 1024 * 1024;
                const totalChunks = Math.ceil(file.size / chunkSize);
                const uploadId = Math.random().toString(36).substring(2);
                let currentChunk = 0;

                function uploadNextChunk() {
                    const start = currentChunk * chunkSize;
                    const end = Math.min(file.size, start + chunkSize);
                    const chunk = file.slice(start, end);

                    let formData = new FormData();
                    formData.append('file', chunk);
                    formData.append('chunk_index', currentChunk);
                    formData.append('total_chunks', totalChunks);
                    formData.append('upload_id', uploadId);
                    formData.append('_token', '{{ csrf_token() }}');

                    $.ajax({
                        url: '{{ route("presets.module.import.upload") }}',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        success: function () {
                            currentChunk++;
                            let progress = (currentChunk / totalChunks) * 100;
                            $('#progressBar').css('width', progress.toFixed(0) + '%').text('Загрузка: ' + progress.toFixed(0) + '%');

                            if (currentChunk < totalChunks) {
                                uploadNextChunk();
                            } else {
                                finalizeUpload(uploadId, file.name, totalChunks, callback);
                            }
                        },
                        error: function () {
                            logError('Ошибка загрузки куска файла ' + currentChunk);
                            finishImport(true);
                        }
                    });
                }
                uploadNextChunk();
            }

            function finalizeUpload(uploadId, fileName, totalChunks, callback) {
                $.ajax({
                    url: '{{ route("presets.module.import.finalize") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        upload_id: uploadId,
                        file_name: fileName,
                        total_chunks: totalChunks
                    },
                    success: function (resp) {
                        if (resp.success) callback(resp.file_path);
                        else { logError('Ошибка сборки файла'); finishImport(true); }
                    }
                });
            }

            // --- ФАЗА 2: ЧТЕНИЕ И ОБРАБОТКА ---
            function readChunk(filePath, startRow, configId, isTest) {
                $.ajax({
                    url: '{{ route("presets.module.import.read") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        file_path: filePath,
                        start_row: startRow,
                        config_id: configId
                    },
                    success: function (response) {
                        if (response.success && response.rows.length > 0) {
                            processChunk(response.rows, configId, isTest, function () {
                                totalRows += response.rows.length;
                                $('#statRows').text(totalRows);
                                let progress = Math.min(99, totalRows / 100);
                                $('#progressBar').css('width', progress + '%').text(progress.toFixed(0) + '%');
                                readChunk(filePath, response.next_start_row, configId, isTest);
                            });
                        } else {
                            finishImport(false);
                        }
                    },
                    error: function (xhr) {
                        logError('Критическая ошибка чтения: ' + xhr.responseText);
                        finishImport(true);
                    }
                });
            }

            function processChunk(rows, configId, isTest, callback) {
                let parentId = $('#auto_parent_id').val() || 0;

                $.ajax({
                    url: '{{ route("presets.module.import.process") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        payload: JSON.stringify(rows),
                        config_id: configId,
                        test: isTest,
                        default_parent: parentId
                    },
                    success: function (response) {
                        if (response.success) {
                            totalProducts += response.stats.updated;
                            $('#statProducts').text(totalProducts);
                            if (response.stats.errors.length > 0) {
                                totalErrors += response.stats.errors.length;
                                $('#statErrors').text(totalErrors);
                                response.stats.errors.forEach(err => logError(err));
                            }
                        }
                        callback();
                    },
                    error: function (xhr) {
                        logError('Ошибка обработки: ' + xhr.responseText);
                        callback();
                    }
                });
            }

            function logError(message) {
                $('#errorLog').append('<div>[-] ' + message + '</div>');
                $('#errorLog').scrollTop($('#errorLog')[0].scrollHeight);
            }

            function finishImport(hasError) {
                isRunning = false;
                $('#startImportBtn').prop('disabled', false).html('<i class="fa fa-play"></i> Запустить импорт');
                $('#progressBar').removeClass('active');
                if (hasError) {
                    $('#progressBar').css('width', '100%').text('Остановлено').addClass('progress-bar-danger');
                } else {
                    $('#progressBar').css('width', '100%').text('100% Завершено');
                }
            }
        });
    </script>
@endpush