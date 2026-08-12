<?php

namespace roilafx\Product\Services\Import;

class DataTransformer
{
    public function transformRow(array $rawRow, array $mapping, array $transformers): array
    {
        $transformed = [];
        
        foreach ($mapping as $sourceKey => $targetKey) {
            if (in_array($sourceKey, ['unique_key', 'default_parent'])) {
                continue;
            }

            if (!isset($rawRow[$sourceKey])) {
                $transformed[$targetKey] = null;
                continue;
            }

            $value = $rawRow[$sourceKey];
            
            if (isset($transformers[$targetKey])) {
                foreach ($transformers[$targetKey] as $rule) {
                    $value = $this->applyTransformer($value, $rule);
                }
            }
            
            $transformed[$targetKey] = $value;
        }
        
        return $transformed;
    }

    private function applyTransformer($value, array $rule)
    {
        return match ($rule['type'] ?? '') {
            'trim' => is_string($value) ? trim($value) : $value,
            'floatval' => floatval(preg_replace('/[^0-9.,-]/', '', str_replace(',', '.', (string)$value))),
            'regex_replace' => preg_replace($rule['pattern'] ?? '//', $rule['replacement'] ?? '', (string)$value),
            default => $value
        };
    }
}