<?php

namespace roilafx\Product\Console\Commands;

use Illuminate\Console\Command;
use roilafx\Product\Services\ProductExportService;

class ExportCatalogCommand extends Command
{
    protected $signature = 'product:export {format=csv} {--parent_id=0}';
    protected $description = 'Экспорт каталога в файл (csv, xlsx, xml, json, yml)';

    public function handle(ProductExportService $service)
    {
        $format = strtolower($this->argument('format'));
        $parentId = (int) $this->option('parent_id');

        $formats = ['csv', 'xlsx', 'xml', 'json', 'yml'];
        if (!in_array($format, $formats)) {
            $this->error("Неподдерживаемый формат: {$format}. Доступны: " . implode(', ', $formats));
            return 1;
        }

        $this->info("Начинаем экспорт каталога (Формат: {$format}, Категория ID: {$parentId})...");
        $this->info("Это может занять некоторое время в зависимости от количества товаров.");

        $bar = $this->output->createProgressBar(1);
        $bar->start();

        try {
            $filePath = $service->exportToFile($parentId, $format);
            $bar->finish();
            
            $this->info("\nЭкспорт успешно завершен!");
            $this->info("Файл сохранен: {$filePath}");
        } catch (\Exception $e) {
            $bar->finish();
            $this->error("\nОшибка: " . $e->getMessage());
            return 1;
        }
    }
}