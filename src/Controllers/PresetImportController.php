<?php

namespace roilafx\Product\Controllers;

use EvolutionCMS\TemplateController;
use roilafx\Product\Services\ProductVariantService;
use Illuminate\Http\Request;

class PresetImportController extends TemplateController
{
    public function form()
    {
        $resources = \EvolutionCMS\Models\SiteContent::select('id', 'pagetitle')
            ->where('isfolder', 0)
            ->orderBy('pagetitle')
            ->limit(500)
            ->get();

        $this->setView('products::presets.import');
        $this->addViewData([
            'resources' => $resources,
            'title'     => 'Импорт вариантов из CSV',
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function store(Request $request)
    {
        $productId = $request->input('product_id');
        $file = $request->file('csv_file');

        if (!$productId || !$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Выберите товар и загрузите CSV-файл.');
        }

        $product = \EvolutionCMS\Models\SiteContent::find($productId);
        if (!$product) {
            return redirect()->back()->with('error', 'Товар не найден.');
        }

        $rows = array_map('str_getcsv', file($file->getRealPath()));
        if (count($rows) < 2) {
            return redirect()->back()->with('error', 'CSV-файл должен содержать заголовок и хотя бы одну строку данных.');
        }

        $headers = $rows[0];
        unset($rows[0]);

        $variantService = new ProductVariantService();
        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (empty(array_filter($row))) continue;

            $attrs = [];
            foreach ($headers as $colIndex => $code) {
                $code = trim($code);
                if (empty($code)) continue;
                $attrs[$code] = $row[$colIndex] ?? '';
            }

            if (empty($attrs)) continue;

            try {
                $variantService->createVariant($productId, $attrs);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Строка " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        $message = "Создано вариантов: {$created}.";
        if (!empty($errors)) {
            $message .= " Ошибки: " . implode('; ', array_slice($errors, 0, 5));
        }

        return redirect()->route('presets.module.import')->with('success', $message);
    }
}