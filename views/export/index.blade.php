@extends('products::layouts.app')

@section('icon', 'download')
@section('title', $title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('presets.module.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Назад
        </a>
    </div>
@endsection

@section('content')
    <div class="container-fluid">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="panel panel-primary">
            <div class="panel-heading"><b>Настройки экспорта</b></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>ID категории для выгрузки</label>
                            <input type="number" id="parent_id" class="form-control"
                                placeholder="0 - выгрузить весь каталог">
                            <small class="text-muted">Укажите ID папки, чтобы выгрузить только её (с вложенными).</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Формат файла</label>
                            <select id="format" class="form-control">
                                <option value="csv">CSV (рекомендуется для больших баз)</option>
                                <option value="xlsx">Excel (XLSX)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button id="startExportBtn" class="btn btn-primary btn-block" style="margin-top: 25px;">
                            <i class="fa fa-play"></i> Начать экспорт
                        </button>
                    </div>
                </div>
                <div class="alert alert-info" style="margin-top:15px; margin-bottom:0;">
                    <b>Как это работает:</b> Скрипт соберет товары, их общие характеристики и характеристики вариаций.
                    Заголовки колонок будут иметь префиксы <code>general:</code> и <code>variant:</code>. Если у товара
                    несколько вариаций, они будут выгружены в отдельных строках.
                    Этот файл можно использовать как образец для Импорта.
                </div>
            </div>
        </div>

        <div id="exportStatus" style="display:none; margin-top: 20px;">
            <h4 id="statusTitle">Подготовка данных...</h4>
            <div class="progress" style="height: 30px;">
                <div id="progressBar" class="progress-bar progress-bar-striped active" role="progressbar"
                    style="width: 0%;">0%</div>
            </div>

            <div class="row" style="margin-top: 20px;">
                <div class="col-md-4">
                    <div class="panel panel-info">
                        <div class="panel-heading">Статистика</div>
                        <div class="panel-body">
                            <p>Всего товаров в базе: <span id="statTotal" class="badge">0</span></p>
                            <p>Экспортировано строк: <span id="statProcessed" class="badge">0</span></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="panel panel-warning">
                        <div class="panel-heading">Лог процесса</div>
                        <div class="panel-body" id="processLog"
                            style="max-height: 300px; overflow-y: auto; font-size: 12px; font-family: monospace; background: #f9f9f9;">
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

            $('#startExportBtn').on('click', function () {
                if (isRunning) return;

                isRunning = true;
                $('#exportStatus').show();
                $('#processLog').html('');
                $('#progressBar').css('width', '0%').text('0%').removeClass('progress-bar-danger').addClass('active');
                $('#startExportBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Идет процесс...');

                let parentId = $('#parent_id').val() || 0;
                let format = $('#format').val();

                logMessage('Запуск инициализации экспорта...');

                // Шаг 1: Инициализация
                $.ajax({
                    url: '{{ route("presets.module.export.start") }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', parent_id: parentId },
                    success: function (resp) {
                        if (resp.success) {
                            $('#statTotal').text(resp.total);
                            logMessage('Найдено товаров: ' + resp.total + '. Начинаем сбор данных...');
                            processChunk(resp.export_id, resp.total, 0, format);
                        } else {
                            logMessage('Ошибка: ' + resp.message, true);
                            finishExport(true);
                        }
                    },
                    error: function () {
                        logMessage('Критическая ошибка при инициализации.', true);
                        finishExport(true);
                    }
                });
            });

            // Шаг 2: Обработка чанками
            function processChunk(exportId, total, offset, format) {
                $.ajax({
                    url: '{{ route("presets.module.export.process") }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', export_id: exportId },
                    success: function (resp) {
                        if (resp.success) {
                            $('#statProcessed').text(resp.offset);
                            let progress = (resp.offset / total) * 100;
                            if (progress > 100) progress = 100;
                            $('#progressBar').css('width', progress.toFixed(0) + '%').text(progress.toFixed(0) + '%');

                            logMessage('Обработано чанк: ' + resp.processed + ' строк (всего ' + resp.offset + '/' + total + ')');

                            if (!resp.finished) {
                                processChunk(exportId, total, resp.offset, format);
                            } else {
                                logMessage('Сбор данных завершен. Формирование файла...');
                                downloadFile(exportId, format);
                            }
                        } else {
                            logMessage('Ошибка обработки: ' + resp.message, true);
                            finishExport(true);
                        }
                    },
                    error: function () {
                        logMessage('Критическая ошибка при обработке чанка.', true);
                        finishExport(true);
                    }
                });
            }

            // Шаг 3: Скачивание файла
            function downloadFile(exportId, format) {
                logMessage('Файл готов! Запуск скачивания...');
                if (typeof window.dontShowWorker === 'undefined') {
                    window.dontShowWorker = true;
                }
                window.location.href = '{{ route("presets.module.export.download") }}?export_id=' + exportId + '&format=' + format;
                setTimeout(function () {
                    finishExport(false);
                    if (top.mainMenu && typeof top.mainMenu.stopWork === 'function') {
                        top.mainMenu.stopWork();
                    }
                }, 2000);
            }

            function logMessage(message, isError = false) {
                let color = isError ? '#dc3545' : '#333';
                $('#processLog').append('<div style="color:' + color + ';">[' + new Date().toLocaleTimeString() + '] ' + message + '</div>');
                $('#processLog').scrollTop($('#processLog')[0].scrollHeight);
            }

            function finishExport(hasError) {
                isRunning = false;
                $('#startExportBtn').prop('disabled', false).html('<i class="fa fa-play"></i> Начать экспорт');
                $('#progressBar').removeClass('active');
                if (hasError) {
                    $('#progressBar').css('width', '100%').text('Остановлено').addClass('progress-bar-danger');
                } else {
                    $('#progressBar').css('width', '100%').text('100% Завершено');
                    logMessage('Экспорт успешно завершен!');
                }
            }
        });
    </script>
@endpush