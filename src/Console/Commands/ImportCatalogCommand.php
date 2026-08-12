<?php

namespace roilafx\Product\Console\Commands;

use Illuminate\Console\Command;
use roilafx\Product\Services\Import\ImportOrchestrator;
use roilafx\Product\Services\Import\DataTransformer;
use roilafx\Product\Models\ProductImportConfig;
use roilafx\Product\Models\Attribute;
use JsonMachine\Items;
use Illuminate\Support\Str;
use XMLReader;

class ImportCatalogCommand extends Command
{
    protected $signature = 'product:import {file} {--map=} {--create-attrs} {--sync-mode=incremental} {--test}';
    protected $description = 'Импорт каталога v4.0 (CLI, потоковое чтение)';

    private DataTransformer $transformer;
    private ImportOrchestrator $orchestrator;
    private array $mapping = [];
    private array $transformers = [];

    public function handle(ImportOrchestrator $orchestrator, DataTransformer $transformer)
    {
        $this->orchestrator = $orchestrator;
        $this->transformer = $transformer;
        
        $file = $this->argument('file');
        $syncMode = $this->option('sync-mode');
        $isTest = $this->option('test');
        $createAttrs = $this->option('create-attrs');

        $configId = $this->option('map');
        if ($configId && $config = ProductImportConfig::find($configId)) {
            $this->mapping = $config->mapping;
            $this->transformers = $config->transformers ?? [];
        } else {
            $this->error('Укажите корректный --map={ID конфига}');
            return 1;
        }

        if (!file_exists($file)) {
            $this->error("Файл не найден: {$file}");
            return 1;
        }

        $this->info("Начало импорта. Режим: {$syncMode}. Тест: " . ($isTest ? 'Да' : 'Нет'));

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $groupedProducts = [];
        $processedCount = 0;

        $bar = $this->output->createProgressBar();
        $bar->start();

        if ($extension === 'json') {
            $products = Items::fromFile($file, ['pointer' => '/products']);
            foreach ($products as $productData) {
                $mapped = $this->processRow((array)$productData);
                if ($mapped) {
                    $groupedProducts = array_merge($groupedProducts, $mapped);
                    $processedCount++;
                }
                $this->processBatchIfReady($groupedProducts, $syncMode, $isTest, $bar);
            }
        } elseif (in_array($extension, ['xml', 'yml'])) {
            $this->parseXmlStream($file, $groupedProducts, $syncMode, $isTest, $bar);
        } elseif ($extension === 'csv') {
            $this->parseCsvStream($file, $groupedProducts, $syncMode, $isTest, $bar);
        } else {
            $this->error("Формат {$extension} не поддерживается в CLI.");
            return 1;
        }

        // Обработка остатка
        if (!empty($groupedProducts)) {
            $this->orchestrator->processChunk($groupedProducts, $syncMode, $isTest);
        }

        $bar->finish();
        $this->info("\nИмпорт завершен. Обработано товаров: {$processedCount}");
    }

    private function processBatchIfReady(array &$groupedProducts, string $syncMode, bool $isTest, $bar)
    {
        if (count($groupedProducts) >= 500) {
            $this->orchestrator->processChunk($groupedProducts, $syncMode, $isTest);
            $groupedProducts = [];
            gc_collect_cycles();
        }
        $bar->advance();
    }

    private function parseCsvStream(string $file, array &$groupedProducts, string $syncMode, bool $isTest, $bar)
    {
        $handle = fopen($file, 'r');
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $mapped = $this->processRow(array_values($row));
            if ($mapped) {
                $groupedProducts = array_merge($groupedProducts, $mapped);
            }
            $this->processBatchIfReady($groupedProducts, $syncMode, $isTest, $bar);
        }
        fclose($handle);
    }

    private function parseXmlStream(string $file, array &$groupedProducts, string $syncMode, bool $isTest, $bar)
    {
        $reader = new XMLReader();
        $reader->open($file);

        $currentElement = '';
        $currentData = [];

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && in_array($reader->name, ['offer', 'product'])) {
                $currentElement = $reader->name;
                $currentData = [];
            } elseif ($reader->nodeType == XMLReader::ELEMENT && $currentElement) {
                $fieldName = $reader->name;
                $reader->read();
                if ($reader->nodeType == XMLReader::TEXT) {
                    $currentData[$fieldName] = $reader->value;
                }
            } elseif ($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == $currentElement) {
                $currentElement = '';
                $mapped = $this->processRow($currentData);
                if ($mapped) {
                    $groupedProducts = array_merge($groupedProducts, $mapped);
                }
                $this->processBatchIfReady($groupedProducts, $syncMode, $isTest, $bar);
            }
        }
        $reader->close();
    }

    private function processRow(array $rawData): array
    {
        $transformed = $this->transformer->transformRow($rawData, $this->mapping, $this->transformers);
        $grouped = [];
        
        $system = [];
        $general = [];
        $variant = [];
        $uniqueValue = null;

        $uniqueKeyTarget = $this->mapping['unique_key'] ?? 'pagetitle';
        $uniqueKeyAttrId = null;
        if (Str::startsWith($uniqueKeyTarget, 'general:')) {
            $code = Str::after($uniqueKeyTarget, 'general:');
            $uniqueKeyAttrId = Attribute::where('code', $code)->value('id');
        }

        foreach ($transformed as $target => $value) {
            if (in_array($target, ['pagetitle', 'parent', 'template', 'published', 'alias', 'introtext'])) {
                $system[$target] = $value;
            } elseif (Str::startsWith($target, 'general:')) {
                $code = Str::after($target, 'general:');
                $general[$code] = $value;
                if ($target === $uniqueKeyTarget) $uniqueValue = $value;
            } elseif (Str::startsWith($target, 'variant:')) {
                $code = Str::after($target, 'variant:');
                $variant[$code] = $value;
            }
        }
        
        if (empty($system['parent'])) {
            $system['parent'] = $this->mapping['default_parent'] ?? 0;
        }

        $groupKey = $uniqueValue ?: ($system['pagetitle'] ?? null);
        if (!$groupKey) return [];

        $grouped[$groupKey] = [
            'system' => $system,
            'general' => $general,
            'variants' => !empty($variant) ? [$variant] : [],
            'unique_key' => $groupKey,
            'unique_key_attr_id' => $uniqueKeyAttrId,
            'unique_key_value' => $uniqueValue
        ];
        
        return $grouped;
    }
}