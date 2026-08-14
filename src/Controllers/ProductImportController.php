<?php

namespace roilafx\Product\Controllers;

use EvolutionCMS\TemplateController;
use Illuminate\Http\Request;
use roilafx\Product\Services\Import\ImportOrchestrator;
use roilafx\Product\Services\Import\DataTransformer;
use roilafx\Product\Models\ProductImportConfig;
use roilafx\Product\Models\Attribute;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use roilafx\Product\Responses\ApiResponse;

class ProductImportController extends TemplateController
{
    private ImportOrchestrator $orchestrator;
    private DataTransformer $transformer;
    private ApiResponse $apiResponse;

    public function __construct(ImportOrchestrator $orchestrator, DataTransformer $transformer, ApiResponse $apiResponse)
    {
        $this->orchestrator = $orchestrator;
        $this->transformer = $transformer;
        $this->apiResponse = $apiResponse;
    }

    public function index()
    {
        $this->setView('products::import.index');
        $this->addViewData([
            'configs' => ProductImportConfig::orderBy('name')->get(),
            'title'   => 'Импорт каталога'
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function uploadChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'upload_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first(), 422);
        }

        $dir = EVO_STORAGE_PATH . '/imports/tmp_' . $request->upload_id;
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $request->file('file')->move($dir, 'chunk_' . $request->chunk_index);

        return $this->apiResponse->success(null, 200);
    }

    public function finalizeUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
            'file_name' => 'required|string',
            'total_chunks' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first(), 422);
        }

        $tmpDir = EVO_STORAGE_PATH . '/imports/tmp_' . $request->upload_id;
        $finalDir = EVO_STORAGE_PATH . '/imports';

        if (!is_dir($finalDir)) mkdir($finalDir, 0775, true);

        $safeName = time() . '_' . Str::slug(pathinfo($request->file_name, PATHINFO_FILENAME)) . '.' . pathinfo($request->file_name, PATHINFO_EXTENSION);
        $finalPath = $finalDir . '/' . $safeName;

        $out = fopen($finalPath, 'wb');
        if ($out === false) {
            return $this->apiResponse->error('Не удалось создать файл', 500);
        }

        for ($i = 0; $i < $request->total_chunks; $i++) {
            $chunkPath = $tmpDir . '/chunk_' . $i;
            if (!file_exists($chunkPath)) {
                fclose($out);
                return $this->apiResponse->error('Потерян кусок ' . $i, 500);
            }
            $in = fopen($chunkPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            unlink($chunkPath);
        }
        fclose($out);
        rmdir($tmpDir);

        return $this->apiResponse->success(['file_path' => $safeName]);
    }

    public function readChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
            'start_row' => 'required|integer',
            'config_id' => 'nullable|exists:product_import_configs,id'
        ]);

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first(), 422);
        }

        $filePath = EVO_STORAGE_PATH . '/imports/' . basename($request->file_path);

        if (!file_exists($filePath)) {
            return $this->apiResponse->error('Файл не найден', 404);
        }

        if ($request->filled('config_id')) {
            $config = ProductImportConfig::find($request->config_id);
            $mapping = $config->mapping;
        } else {
            $mapping = ['unique_key' => 'pagetitle', 'pagetitle' => 'pagetitle'];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $rows = [];
        $currentRow = $request->start_row;
        $chunkSize = 500;
        $maxRows = $currentRow + $chunkSize;
        $monsterLimit = 2000;

        if ($extension === 'csv') {
            $handle = fopen($filePath, 'r');

            $firstLine = fgets($handle);
            rewind($handle);

            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            $headers = fgetcsv($handle, 0, $delimiter);
            $headers = array_map('trim', $headers);
            if (isset($headers[0])) $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);

            for ($i = 2; $i < $currentRow; $i++) {
                fgetcsv($handle, 0, $delimiter);
            }

            while ($currentRow < $maxRows) {
                $rowData = fgetcsv($handle, 0, $delimiter);
                if ($rowData === false) break;

                if (count($headers) == count($rowData)) {
                    $rows[] = array_combine($headers, $rowData);
                }
                $currentRow++;
            }

            $rows = $this->smartChunkLookup($rows, $handle, $mapping, $monsterLimit, $currentRow, $delimiter);
            fclose($handle);
        } else {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestCol = $sheet->getHighestColumn(1);

            $headers = $sheet->rangeToArray('A1:' . $highestCol . '1', null, true, false)[0];
            $headers = array_map('trim', $headers);
            if (isset($headers[0])) $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);

            while ($currentRow < $maxRows) {
                $rowData = $sheet->rangeToArray('A' . $currentRow . ':' . $highestCol . $currentRow, null, true, false);
                if (empty($rowData[0]) || empty(array_filter($rowData[0]))) break;

                $rowValues = array_pad($rowData[0], count($headers), null);

                if (count($headers) == count($rowValues)) {
                    $rows[] = array_combine($headers, $rowValues);
                }
                $currentRow++;
            }

            if (!empty($rows)) {
                $lastKey = $this->extractUniqueKey(end($rows), $mapping);
                if ($lastKey) {
                    $lookAheadRow = $currentRow;
                    $countForLastKey = 1;
                    while ($countForLastKey < $monsterLimit) {
                        $nextRowData = $sheet->rangeToArray('A' . $lookAheadRow . ':' . $highestCol . $lookAheadRow, null, true, false);
                        if (empty($nextRowData[0]) || empty(array_filter($nextRowData[0]))) break;

                        $nextAssocRow = array_combine($headers, array_pad($nextRowData[0], count($headers), null));
                        $nextKey = $this->extractUniqueKey($nextAssocRow, $mapping);

                        if ($nextKey === $lastKey) {
                            $rows[] = $nextAssocRow;
                            $lookAheadRow++;
                            $countForLastKey++;
                        } else {
                            break;
                        }
                    }
                    if ($countForLastKey >= $monsterLimit) {
                        $rows = array_slice($rows, 0, count($rows) - $countForLastKey);
                        $currentRow += $countForLastKey - 1;
                    } else {
                        $currentRow = $lookAheadRow;
                    }
                }
            }
        }

        return $this->apiResponse->success([
            'rows' => $rows,
            'next_start_row' => $currentRow
        ]);
    }

    private function smartChunkLookup(array $rows, $handle, array $mapping, int $monsterLimit, int &$currentRow, string $delimiter = ','): array
    {
        if (empty($rows)) return $rows;
        $lastKey = $this->extractUniqueKey(end($rows), $mapping);
        if (!$lastKey) return $rows;

        $countForLastKey = 1;
        while ($countForLastKey < $monsterLimit) {
            $nextRowData = fgetcsv($handle, 1000, $delimiter);
            if ($nextRowData === false) break;
            $nextKey = $this->extractUniqueKey($nextRowData, $mapping);
            if ($nextKey === $lastKey) {
                $rows[] = $nextRowData;
                $currentRow++;
                $countForLastKey++;
            } else {
                break;
            }
        }
        if ($countForLastKey >= $monsterLimit) {
            $rows = array_slice($rows, 0, count($rows) - $countForLastKey);
        }
        return $rows;
    }

    public function processChunk(Request $request)
    {
        if ($request->filled('payload')) {
            $rows = json_decode($request->input('payload'), true);
        } else {
            $rows = $request->input('rows', []);
        }

        $isTest = (bool) $request->input('test', false);
        $defaultParent = (int) $request->input('default_parent', 0);

        if ($request->filled('config_id')) {
            $config = ProductImportConfig::find($request->input('config_id'));
            $groupedProducts = $this->groupRows($rows, $config);
            $syncMode = $config->sync_mode;
        } else {
            $mappingArray = [];
            if (!empty($rows)) {
                $firstRow = $rows[0];
                foreach (array_keys($firstRow) as $key) {
                    if (!empty($key)) {
                        $mappingArray[$key] = $key;
                    }
                }
            }
            $mappingArray['unique_key'] = 'pagetitle';
            $mappingArray['default_parent'] = $defaultParent;

            $autoConfig = new ProductImportConfig();
            $autoConfig->mapping = $mappingArray;
            $autoConfig->transformers = [];
            $autoConfig->sync_mode = 'incremental';

            $groupedProducts = $this->groupRows($rows, $autoConfig);
            $syncMode = 'incremental';
        }

        if (!empty($rows) && empty($groupedProducts)) {
            return $this->apiResponse->error(
                'Не удалось сгруппировать товары. Проверьте, что в файле есть колонка "pagetitle".',
                422
            );
        }

        $stats = $this->orchestrator->processChunk($groupedProducts, $syncMode, $isTest);

        return $this->apiResponse->success(['stats' => $stats]);
    }

    public function createConfig()
    {
        $this->setView('products::import.config_form');
        $this->addViewData([
            'config' => null,
            'attributes' => Attribute::orderBy('name')->get(),
            'title'  => 'Создание профиля импорта'
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function editConfig($id)
    {
        $config = ProductImportConfig::findOrFail($id);
        $this->setView('products::import.config_form');
        $this->addViewData([
            'config' => $config,
            'attributes' => Attribute::orderBy('name')->get(),
            'title'  => 'Редактирование профиля: ' . $config->name
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function storeConfig(Request $request)
    {
        $mapping = $this->buildMappingArray($request);

        ProductImportConfig::create([
            'name'         => $request->input('name'),
            'source_type'  => $request->input('source_type'),
            'sync_mode'    => $request->input('sync_mode'),
            'create_attrs' => $request->has('create_attrs'),
            'mapping'      => $mapping
        ]);

        return redirect()->route('presets.module.import')->with('success', 'Профиль создан');
    }

    public function updateConfig(Request $request, $id)
    {
        $config = ProductImportConfig::findOrFail($id);
        $mapping = $this->buildMappingArray($request);

        $config->update([
            'name'         => $request->input('name'),
            'source_type'  => $request->input('source_type'),
            'sync_mode'    => $request->input('sync_mode'),
            'create_attrs' => $request->has('create_attrs'),
            'mapping'      => $mapping
        ]);

        return redirect()->route('presets.module.import')->with('success', 'Профиль обновлен');
    }

    private function buildMappingArray(Request $request): array
    {
        $mapping = [];
        $sources = $request->input('source', []);
        $targets = $request->input('target', []);

        foreach ($sources as $index => $source) {
            if (!empty($source) && !empty($targets[$index])) {
                $mapping[$source] = $targets[$index];
            }
        }

        $mapping['unique_key'] = $request->input('unique_key', 'pagetitle');
        $mapping['default_parent'] = (int) $request->input('default_parent_id', 0);

        return $mapping;
    }

    private function extractUniqueKey(array $row, array $mapping): ?string
    {
        $uniqueKeyTarget = $mapping['unique_key'] ?? 'pagetitle';
        $sourceCol = array_search($uniqueKeyTarget, $mapping);
        if ($sourceCol === false || !isset($row[$sourceCol])) return null;
        return (string)$row[$sourceCol];
    }

    private function groupRows(array $rows, ProductImportConfig $config): array
    {
        $grouped = [];
        $mapping = $config->mapping;
        $transformers = $config->transformers ?? [];

        $uniqueKeyTarget = $mapping['unique_key'] ?? 'pagetitle';
        $defaultParent = $mapping['default_parent'] ?? 0;

        $uniqueKeyAttrId = null;
        if (Str::startsWith($uniqueKeyTarget, 'general:')) {
            $code = Str::after($uniqueKeyTarget, 'general:');
            $uniqueKeyAttrId = Attribute::where('code', $code)->value('id');
        }

        foreach ($rows as $index => $row) {
            $transformed = $this->transformer->transformRow($row, $mapping, $transformers);

            $system = [];
            $general = [];
            $variant = [];
            $uniqueValue = null;

            foreach ($transformed as $target => $value) {
                if (in_array($target, ['pagetitle', 'parent', 'template', 'published', 'alias', 'introtext'])) {
                    $system[$target] = $value;
                } elseif (Str::startsWith($target, 'general:')) {
                    $code = Str::after($target, 'general:');
                    if ($value !== null && $value !== '') {
                        $general[$code] = $value;
                    }
                    if ($target === $uniqueKeyTarget) $uniqueValue = $value;
                } elseif (Str::startsWith($target, 'variant:')) {
                    $code = Str::after($target, 'variant:');
                    if ($value !== null && $value !== '') {
                        $variant[$code] = $value;
                    }
                }
            }

            if (empty($system['parent'])) {
                $system['parent'] = $defaultParent;
            }

            $groupKey = $uniqueValue ?: ($system['pagetitle'] ?? null);
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'system' => $system,
                    'general' => $general,
                    'variants' => [],
                    'unique_key' => $groupKey,
                    'unique_key_attr_id' => $uniqueKeyAttrId,
                    'unique_key_value' => $uniqueValue
                ];
            } else {
                $grouped[$groupKey]['general'] = array_merge($grouped[$groupKey]['general'], $general);
            }
            if (!empty($variant)) $grouped[$groupKey]['variants'][] = $variant;
        }
        return array_values($grouped);
    }
}