<?php

namespace roilafx\Product\Services;

use EvolutionCMS\Models\SiteContent;
use roilafx\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductExportService
{
    public function getTotalProducts(int $parentId = 0): int
    {
        $query = SiteContent::where('published', 1)->where('deleted', 0)->where('isfolder', 0);
        if ($parentId > 0) {
            $categoryIds = DB::table('site_content_closure')
                ->where('ancestor', $parentId)->pluck('descendant')->toArray();
            $categoryIds[] = $parentId;
            $query->whereIn('parent', $categoryIds);
        }
        return $query->count();
    }

    public function getHeaders(int $parentId = 0): array
    {
        $query = SiteContent::where('published', 1)->where('deleted', 0)->where('isfolder', 0);
        if ($parentId > 0) {
            $categoryIds = DB::table('site_content_closure')
                ->where('ancestor', $parentId)->pluck('descendant')->toArray();
            $categoryIds[] = $parentId;
            $query->whereIn('parent', $categoryIds);
        }
        $productIds = $query->pluck('id')->toArray();

        $generalAttrs = DB::table('product_attributes')
            ->join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
            ->whereIn('product_attributes.product_id', $productIds)
            ->select('attributes.code')->distinct()->pluck('code')->toArray();

        $variantAttrs = DB::table('variant_attribute_values')
            ->join('attributes', 'attributes.id', '=', 'variant_attribute_values.attribute_id')
            ->join('product_variants', 'product_variants.id', '=', 'variant_attribute_values.variant_id')
            ->whereIn('product_variants.product_id', $productIds)
            ->select('attributes.code')->distinct()->pluck('code')->toArray();

        $headers = ['pagetitle', 'parent', 'template', 'published', 'introtext'];
        foreach ($generalAttrs as $code) $headers[] = 'general:' . $code;
        foreach ($variantAttrs as $code) $headers[] = 'variant:' . $code;

        return $headers;
    }

    public function getChunk(int $parentId, int $offset, int $limit, array $headers): array
    {
        $query = SiteContent::where('published', 1)->where('deleted', 0)->where('isfolder', 0);
        if ($parentId > 0) {
            $categoryIds = DB::table('site_content_closure')
                ->where('ancestor', $parentId)->pluck('descendant')->toArray();
            $categoryIds[] = $parentId;
            $query->whereIn('parent', $categoryIds);
        }
        $products = $query->orderBy('id')->skip($offset)->take($limit)->get();

        $generalCodes = [];
        $variantCodes = [];
        foreach ($headers as $h) {
            if (str_starts_with($h, 'general:')) $generalCodes[] = substr($h, 8);
            if (str_starts_with($h, 'variant:')) $variantCodes[] = substr($h, 8);
        }

        $rows = [];
        foreach ($products as $product) {
            $baseRow = [
                $product->pagetitle,
                $product->parent,
                $product->template,
                $product->published,
                $product->introtext
            ];

            $generalVals = DB::table('product_attributes')
                ->join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
                ->where('product_id', $product->id)
                ->whereIn('attributes.code', $generalCodes)
                ->pluck('value', 'attributes.code');

            foreach ($generalCodes as $code) {
                $baseRow[] = $generalVals[$code] ?? '';
            }

            $variants = ProductVariant::where('product_id', $product->id)->where('active', 1)->get();
            if ($variants->isEmpty()) {
                foreach ($variantCodes as $code) $baseRow[] = '';
                $rows[] = $baseRow;
            } else {
                foreach ($variants as $variant) {
                    $variantRow = $baseRow;
                    $variantVals = DB::table('variant_attribute_values')
                        ->join('attributes', 'attributes.id', '=', 'variant_attribute_values.attribute_id')
                        ->where('variant_id', $variant->id)
                        ->whereIn('attributes.code', $variantCodes)
                        ->pluck('value', 'attributes.code');
                    foreach ($variantCodes as $code) {
                        $variantRow[] = $variantVals[$code] ?? '';
                    }
                    $rows[] = $variantRow;
                }
            }
        }
        return $rows;
    }

    public function exportToFile(int $parentId = 0, string $format = 'csv'): string
    {
        $dir = EVO_STORAGE_PATH . '/exports';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $fileName = 'export_catalog_' . date('Y-m-d_H-i-s') . '.' . $format;
        $filePath = $dir . '/' . $fileName;

        $query = SiteContent::where('published', 1)->where('deleted', 0)->where('isfolder', 0);
        if ($parentId > 0) {
            $categoryIds = DB::table('site_content_closure')
                ->where('ancestor', $parentId)->pluck('descendant')->toArray();
            $categoryIds[] = $parentId;
            $query->whereIn('parent', $categoryIds);
        }

        $total = $query->count();
        if ($total === 0) {
            throw new \Exception("Нет товаров для экспорта в выбранной категории.");
        }

        $productIds = $query->pluck('id')->toArray();
        $generalCodes = DB::table('product_attributes')
            ->join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
            ->whereIn('product_attributes.product_id', $productIds)
            ->pluck('attributes.code')->unique()->toArray();

        $variantCodes = DB::table('variant_attribute_values')
            ->join('attributes', 'attributes.id', '=', 'variant_attribute_values.attribute_id')
            ->join('product_variants', 'product_variants.id', '=', 'variant_attribute_values.variant_id')
            ->whereIn('product_variants.product_id', $productIds)
            ->pluck('attributes.code')->unique()->toArray();

        $limit = 500;
        $offset = 0;

        if (in_array($format, ['csv', 'xlsx'])) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['pagetitle', 'parent', 'template', 'published', 'introtext'];
            foreach ($generalCodes as $code) $headers[] = 'general:' . $code;
            foreach ($variantCodes as $code) $headers[] = 'variant:' . $code;

            $sheet->fromArray($headers, NULL, 'A1');
            $rowIndex = 2;

            while (true) {
                $products = (clone $query)->skip($offset)->take($limit)->get();
                if ($products->isEmpty()) break;

                $rows = [];
                foreach ($this->generateRows($products, $generalCodes, $variantCodes) as $row) {
                    $rows[] = array_values($row);
                }
                $sheet->fromArray($rows, NULL, 'A' . $rowIndex);
                $rowIndex += count($rows);
                $offset += $limit;
            }

            $writer = ($format === 'csv') ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save($filePath);
        } elseif ($format === 'json') {
            $fp = fopen($filePath, 'w');
            fwrite($fp, '{"products": [');
            $first = true;
            while (true) {
                $products = (clone $query)->skip($offset)->take($limit)->get();
                if ($products->isEmpty()) break;

                foreach ($this->generateRows($products, $generalCodes, $variantCodes) as $row) {
                    if (!$first) fwrite($fp, ',');
                    $cleanRow = array_filter($row, function ($val) {
                        return $val !== '' && $val !== null;
                    });
                    fwrite($fp, json_encode($cleanRow, JSON_UNESCAPED_UNICODE));
                    $first = false;
                }
                $offset += $limit;
            }
            fwrite($fp, ']}');
            fclose($fp);
        } elseif (in_array($format, ['xml', 'yml'])) {
            $xml = new \XMLWriter();
            $xml->openURI($filePath);
            $xml->startDocument('1.0', 'UTF-8');

            if ($format === 'yml') {
                $xml->startElement('yml_catalog');
                $xml->writeAttribute('date', date('Y-m-d H:i'));
                $xml->startElement('shop');
                $xml->startElement('offers');
            } else {
                $xml->startElement('products');
            }

            while (true) {
                $products = (clone $query)->skip($offset)->take($limit)->get();
                if ($products->isEmpty()) break;

                foreach ($this->generateRows($products, $generalCodes, $variantCodes) as $row) {
                    $xml->startElement($format === 'yml' ? 'offer' : 'product');
                    foreach ($row as $key => $val) {
                        if ($val === '' || $val === null) continue;
                        $tag = str_replace([':', ' '], '_', $key);
                        $xml->writeElement($tag, $val);
                    }
                    $xml->endElement();
                }
                $offset += $limit;
            }

            if ($format === 'yml') {
                $xml->endElement();
                $xml->endElement();
                $xml->endElement();
            } else {
                $xml->endElement();
            }
            $xml->endDocument();
        } else {
            throw new \Exception("Формат {$format} не поддерживается.");
        }

        return $filePath;
    }

    /**
     * Генератор строк для экономии памяти при CLI экспорте
     */
    protected function generateRows($products, array $generalCodes, array $variantCodes): \Generator
    {
        foreach ($products as $product) {
            $baseRow = [
                'pagetitle' => $product->pagetitle,
                'parent' => $product->parent,
                'template' => $product->template,
                'published' => $product->published,
                'introtext' => $product->introtext,
            ];

            $generalVals = DB::table('product_attributes')
                ->join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
                ->where('product_id', $product->id)
                ->whereIn('attributes.code', $generalCodes)
                ->pluck('value', 'attributes.code');

            foreach ($generalCodes as $code) {
                $baseRow['general:' . $code] = $generalVals[$code] ?? '';
            }

            $variants = ProductVariant::where('product_id', $product->id)->where('active', 1)->get();

            if ($variants->isEmpty()) {
                $row = $baseRow;
                foreach ($variantCodes as $code) {
                    $row['variant:' . $code] = '';
                }
                yield $row;
            } else {
                foreach ($variants as $variant) {
                    $row = $baseRow;
                    $variantVals = DB::table('variant_attribute_values')
                        ->join('attributes', 'attributes.id', '=', 'variant_attribute_values.attribute_id')
                        ->where('variant_id', $variant->id)
                        ->whereIn('attributes.code', $variantCodes)
                        ->pluck('value', 'attributes.code');

                    foreach ($variantCodes as $code) {
                        $row['variant:' . $code] = $variantVals[$code] ?? '';
                    }
                    yield $row;
                }
            }
        }
    }
}
