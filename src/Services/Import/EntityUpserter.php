<?php

namespace roilafx\Product\Services\Import;

use EvolutionCMS\DocumentManager\Facades\DocumentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntityUpserter
{
    public function upsertProduct(array $productData, DictionaryIndex $dictionary, bool $dryRun): ?int
    {
        $systemFields = $productData['system'] ?? [];
        if (empty($systemFields['pagetitle'])) return null;

        $parentId = $dictionary->resolveParent($systemFields['parent'] ?? 0);
        
        if ($parentId === 0) {
            throw new \InvalidArgumentException("Категория не найдена или равна 0 для товара: " . $systemFields['pagetitle']);
        }

        $uniqueKeyAttrId = $productData['unique_key_attr_id'] ?? null;
        $uniqueKeyValue = $productData['unique_key_value'] ?? null;

        $query = DB::table('site_content')->where('parent', $parentId);
        
        if ($uniqueKeyAttrId && $uniqueKeyValue) {
            $query->whereExists(function ($q) use ($uniqueKeyAttrId, $uniqueKeyValue) {
                $q->select(DB::raw(1))
                  ->from('product_attributes')
                  ->whereColumn('product_attributes.product_id', 'site_content.id')
                  ->where('attribute_id', $uniqueKeyAttrId)
                  ->where('value_hash', md5($uniqueKeyValue));
            });
        } else {
            $query->where('pagetitle', $systemFields['pagetitle']);
        }

        $productId = $query->value('id');

        if ($dryRun) return $productId ?: -1;

        $docData = [
            'pagetitle'  => $systemFields['pagetitle'],
            'parent'     => $parentId,
            'template'   => $systemFields['template'] ?? 3,
            'published'  => $systemFields['published'] ?? 1,
        ];

        try {
            if ($productId) {
                $docData['id'] = $productId;
                DocumentManager::edit($docData);
                return $productId;
            } else {
                $docData['alias'] = Str::slug($systemFields['pagetitle']) . '-' . Str::random(6);
                $docData['alias_visible'] = 1;
                $docData['isfolder'] = 0;
                $docData['content_type'] = 'text/html';
                
                $document = DocumentManager::create($docData);
                return $document->id;
            }
        } catch (\EvolutionCMS\Exceptions\ServiceValidationException $e) {
            $errors = is_array($e->getValidationErrors()) ? implode(', ', $e->getValidationErrors()) : 'Неизвестная ошибка валидации';
            throw new \Exception("Ошибка валидации EVO [{$systemFields['pagetitle']}]: " . $errors);
        } catch (\EvolutionCMS\Exceptions\ServiceActionException $e) {
            throw new \Exception("Ошибка действия EVO [{$systemFields['pagetitle']}]: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception("Общая ошибка создания [{$systemFields['pagetitle']}]: " . $e->getMessage());
        }
    }

    public function upsertGeneralAttributes(int $productId, array $generalAttrs, DictionaryIndex $dictionary, bool $createAttrs, bool $dryRun): void
    {
        if (empty($generalAttrs)) return;

        $rows = [];
        foreach ($generalAttrs as $code => $value) {
            if ($value === null || $value === '') continue;
            
            $attrId = $dictionary->getAttributeId($code, $createAttrs);
            if (!$attrId) continue;

            $rows[] = [
                'product_id' => $productId,
                'attribute_id' => $attrId,
                'value' => (string)$value,
                'value_numeric' => is_numeric($value) ? (float)$value : null,
            ];
        }

        if (!empty($rows) && !$dryRun) {
            DB::table('product_attributes')->upsert($rows, ['product_id', 'attribute_id'], ['value', 'value_numeric']);
        }
    }
}