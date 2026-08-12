<?php

namespace roilafx\Product\Services\Import;

use Illuminate\Support\Facades\DB;
use roilafx\Product\Models\Attribute;

class DictionaryIndex
{
    protected array $attrCache = [];
    protected array $parentCache = [];
    protected array $missingAttrs = [];

    public function loadAttributes(array $codes): void
    {
        $codes = array_filter($codes);
        if (empty($codes)) return;
        
        $existing = Attribute::whereIn('code', $codes)->pluck('id', 'code')->toArray();
        $this->attrCache = array_merge($this->attrCache, $existing);
        
        $this->missingAttrs = array_diff($codes, array_keys($this->attrCache));
    }

    public function getAttributeId(string $code, bool $createIfNotExists = false): ?int
    {
        if (isset($this->attrCache[$code])) {
            return $this->attrCache[$code];
        }

        if ($createIfNotExists && in_array($code, $this->missingAttrs)) {
            $attr = Attribute::create([
                'name' => ucfirst(str_replace('_', ' ', $code)),
                'code' => $code,
                'field_type' => 'text'
            ]);
            $this->attrCache[$code] = $attr->id;
            unset($this->missingAttrs[array_search($code, $this->missingAttrs)]);
            return $attr->id;
        }

        return null;
    }

    public function resolveParent($parentIdentifier): int
    {
        if (is_numeric($parentIdentifier)) return (int)$parentIdentifier;
        if (empty($parentIdentifier)) return 0;
        
        if (isset($this->parentCache[$parentIdentifier])) {
            return $this->parentCache[$parentIdentifier];
        }

        if (count($this->parentCache) > 100) {
            array_shift($this->parentCache);
        }

        $id = DB::table('site_content')
            ->where('pagetitle', $parentIdentifier)
            ->orWhere('alias', $parentIdentifier)
            ->value('id');
        
        $resolvedId = $id ?: 0;
        $this->parentCache[$parentIdentifier] = $resolvedId;
        return $resolvedId;
    }
}