<?php

namespace roilafx\Product\Controllers;

use EvolutionCMS\TemplateController;
use Illuminate\Http\Request;
use roilafx\Product\Services\Export\ProductExportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Str;
use roilafx\Product\Responses\ApiResponse;

class ProductExportController extends TemplateController
{
    private ProductExportService $exportService;
    private ApiResponse $apiResponse;

    public function __construct(ProductExportService $exportService, ApiResponse $apiResponse)
    {
        $this->exportService = $exportService;
        $this->apiResponse = $apiResponse;
    }

    public function index()
    {
        $this->setView('products::export.index');
        $this->addViewData(['title' => 'Экспорт каталога']);
        return view($this->getView(), $this->getViewData());
    }

    public function start(Request $request)
    {
        $parentId = (int) $request->input('parent_id', 0);
        $totalProducts = $this->exportService->getTotalProducts($parentId);

        if ($totalProducts === 0) {
            return $this->apiResponse->error('Нет товаров для экспорта', 404);
        }

        $headers = $this->exportService->getHeaders($parentId);
        $exportId = Str::random(40);
        $tempFile = tempnam(sys_get_temp_dir(), 'exp_' . $exportId);

        $handle = fopen($tempFile, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        fclose($handle);

        session(['export_' . $exportId => [
            'parent_id' => $parentId,
            'file' => $tempFile,
            'headers' => $headers,
            'total' => $totalProducts,
            'offset' => 0
        ]]);

        return $this->apiResponse->success([
            'export_id' => $exportId,
            'total' => $totalProducts
        ]);
    }

    public function processChunk(Request $request)
    {
        $exportId = $request->input('export_id');
        $sessionData = session('export_' . $exportId);

        if (!$sessionData) {
            return $this->apiResponse->error('Сессия истекла', 404);
        }

        $limit = 500;
        $rows = $this->exportService->getChunk($sessionData['parent_id'], $sessionData['offset'], $limit, $sessionData['headers']);

        $handle = fopen($sessionData['file'], 'a');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $sessionData['offset'] += count($rows);
        session(['export_' . $exportId => $sessionData]);

        $isFinished = count($rows) < $limit;

        return $this->apiResponse->success([
            'processed' => count($rows),
            'offset' => $sessionData['offset'],
            'finished' => $isFinished
        ]);
    }

    public function download(Request $request)
    {
        $exportId = $request->input('export_id');
        $format = $request->input('format', 'csv');
        $sessionData = session('export_' . $exportId);

        if (!$sessionData || !file_exists($sessionData['file'])) {
            return redirect()->route('presets.module.export')->with('error', 'Файл не найден');
        }

        $csvFile = $sessionData['file'];
        $fileName = 'export_catalog_' . date('Y-m-d_H-i-s') . '.' . $format;

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if (($handle = fopen($csvFile, "r")) !== FALSE) {
                $row = 1;
                while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                }
                fclose($handle);
            }

            $tempXlsx = tempnam(sys_get_temp_dir(), 'xpx_');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempXlsx);
            unlink($csvFile);
            session()->forget('export_' . $exportId);

            return response()->download($tempXlsx, $fileName)->deleteFileAfterSend(true);
        }

        session()->forget('export_' . $exportId);
        return response()->download($csvFile, $fileName)->deleteFileAfterSend(true);
    }
}