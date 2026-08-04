<?php

namespace roilafx\Product\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use EvolutionCMS\Models\SiteContent;

class GenerateTestData extends Command
{
    protected $signature = 'product:generate-test-data';
    protected $description = 'Генерация тестовых товаров, атрибутов, вариантов и пресетов';

    protected int $productsCount = 5000;
    protected int $categoriesCount = 10;
    protected int $maxVariants = 6;
    protected int $variantProbability = 70;
    protected int $attributesPerProduct = 5;
    protected int $maxPresetsPerProduct = 2;

    protected array $categoryIds = [];
    protected array $attributeIds = [];
    protected array $presetIds = [];

    public function handle()
    {
        $this->info('Очистка таблиц...');
        $this->truncateTables();

        $this->info('Начинаю генерацию тестовых данных...');

        DB::transaction(function () {
            $this->createAttributeCategories();
            $this->createAttributes();
            $this->createPresets();
            $this->createCategoryTree();
            $this->createProducts();
        });

        $this->info('Генерация успешно завершена!');
    }

    /**
     * Очистка всех задействованных таблиц.
     */
    protected function truncateTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = [
            'variant_attribute_values',
            'product_variant_attributes',
            'product_variants',
            'product_attributes',
            'product_presets',
            'attribute_preset_attributes',
            'attribute_presets',
            'attributes',
            'attribute_categories',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    // ========== 1. Категории атрибутов ==========
    protected function createAttributeCategories(): void
    {
        $names = ['Физические характеристики', 'Цвет', 'Размер', 'Материал', 'Электроника', 'Упаковка'];
        $ids = [];
        foreach ($names as $name) {
            $id = DB::table('attribute_categories')->insertGetId([
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[] = $id;
        }
        $this->categoryIds = $ids;
        $this->info('Создано категорий атрибутов: ' . count($ids));
    }

    // ========== 2. Атрибуты ==========
    protected function createAttributes(): void
    {
        $attrs = [
            ['name' => 'Вес',         'code' => 'weight',     'field_type' => 'text',   'options' => null],
            ['name' => 'Ширина',      'code' => 'width',      'field_type' => 'text',   'options' => null],
            ['name' => 'Высота',      'code' => 'height',     'field_type' => 'text',   'options' => null],
            ['name' => 'Длина',       'code' => 'length',     'field_type' => 'text',   'options' => null],
            ['name' => 'Цвет',        'code' => 'color',      'field_type' => 'select', 'options' => json_encode(['Красный', 'Синий', 'Зелёный', 'Чёрный', 'Белый'])],
            ['name' => 'Размер',      'code' => 'size',       'field_type' => 'select', 'options' => json_encode(['S', 'M', 'L', 'XL', 'XXL'])],
            ['name' => 'Материал',    'code' => 'material',   'field_type' => 'select', 'options' => json_encode(['Хлопок', 'Полиэстер', 'Кожа', 'Металл'])],
            ['name' => 'Мощность',    'code' => 'power',      'field_type' => 'text',   'options' => null],
            ['name' => 'Напряжение',  'code' => 'voltage',    'field_type' => 'text',   'options' => null],
            ['name' => 'Ёмкость',     'code' => 'capacity',   'field_type' => 'text',   'options' => null],
            ['name' => 'Гарантия',    'code' => 'warranty',   'field_type' => 'text',   'options' => null],
            ['name' => 'Страна',      'code' => 'country',    'field_type' => 'text',   'options' => null],
            ['name' => 'Артикул',     'code' => 'sku',        'field_type' => 'text',   'options' => null],
            ['name' => 'Тип упаковки', 'code' => 'packaging',  'field_type' => 'select', 'options' => json_encode(['Коробка', 'Пакет', 'Блистер'])],
        ];

        $catCount = count($this->categoryIds);
        $ids = [];
        foreach ($attrs as $i => $attr) {
            $id = DB::table('attributes')->insertGetId([
                'name'        => $attr['name'],
                'code'        => $attr['code'],
                'field_type'  => $attr['field_type'],
                'options'     => $attr['options'],
                'category_id' => $this->categoryIds[$i % $catCount], // равномерно по категориям
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $ids[] = $id;
        }
        $this->attributeIds = $ids;
        $this->info('Создано атрибутов: ' . count($ids));
    }

    // ========== 3. Пресеты атрибутов ==========
    protected function createPresets(): void
    {
        $presetDefinitions = [
            ['name' => 'Одежда',   'attribute_codes' => ['color', 'size', 'material']],
            ['name' => 'Электроника', 'attribute_codes' => ['power', 'voltage', 'warranty', 'capacity']],
            ['name' => 'Упаковка', 'attribute_codes' => ['packaging', 'weight']],
            ['name' => 'Габариты', 'attribute_codes' => ['width', 'height', 'length', 'weight']],
        ];

        // Получим id по кодам для быстрого доступа
        $attrByCode = DB::table('attributes')->pluck('id', 'code')->toArray();

        foreach ($presetDefinitions as $def) {
            $presetId = DB::table('attribute_presets')->insertGetId([
                'name'        => $def['name'],
                'description' => 'Автосгенерированный пресет ' . $def['name'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $this->presetIds[] = $presetId;

            $sort = 0;
            foreach ($def['attribute_codes'] as $code) {
                if (!isset($attrByCode[$code])) continue;
                DB::table('attribute_preset_attributes')->insert([
                    'preset_id'        => $presetId,
                    'attribute_id'     => $attrByCode[$code],
                    'sort'             => $sort++,
                    'generation_config' => null,
                    'is_required'      => true,
                ]);
            }
        }
        $this->info('Создано пресетов: ' . count($this->presetIds));
    }

    // ========== 4. Категории товаров (папки) ==========
    protected function createCategoryTree(): void
    {
        // Создадим несколько папок первого уровня внутри parent=2
        for ($i = 0; $i < $this->categoriesCount; $i++) {
            $folder = new SiteContent([
                'type'         => 'document',
                'contentType'  => 'text/html',
                'pagetitle'    => "Категория " . ($i + 1),
                'longtitle'    => '',
                'description'  => '',
                'alias'        => 'cat-' . ($i + 1) . '-' . Str::random(4),
                'published'    => 1,
                'parent'       => 2,
                'isfolder'     => 1,
                'template'     => 2,
                'menuindex'    => $i,
                'hide_from_tree' => 0,
                'privateweb'   => 0,
                'privatemgr'   => 0,
                'content_dispo' => 0,
                'hidemenu'     => 0,
                'alias_visible' => 1,
                'deleted'      => 0,
            ]);
            $folder->save();
            $parentId = $folder->getKey();

            // Создадим 1–2 подкатегории
            $subCount = rand(1, 2);
            for ($j = 0; $j < $subCount; $j++) {
                $sub = new SiteContent([
                    'type'         => 'document',
                    'contentType'  => 'text/html',
                    'pagetitle'    => "Подкатегория {$i}-{$j}",
                    'alias'        => 'subcat-' . $i . '-' . $j . '-' . Str::random(4),
                    'published'    => 1,
                    'parent'       => $parentId,
                    'isfolder'     => 1,
                    'template'     => 2,
                    'menuindex'    => $j,
                    'deleted'      => 0,
                ]);
                $sub->save();
            }
        }

        // Соберём все папки-категории (включая корневую папку 2, если нужно)
        $this->categoryIds = SiteContent::where('parent', '>=', 2)
            ->where('isfolder', 1)
            ->pluck('id')
            ->toArray();

        $this->info('Папок-категорий: ' . count($this->categoryIds));
    }

    // ========== 5. Товары и всё, что с ними связано ==========
    protected function createProducts(): void
    {
        $attrByCode = DB::table('attributes')->pluck('id', 'code')->toArray();
        $attrIds    = array_values($attrByCode);

        // Для вариантов заранее выберем атрибуты, которые могут быть вариантными (select + text)
        $variantCompatibleAttrIds = DB::table('attributes')
            ->whereIn('field_type', ['select', 'text'])
            ->pluck('id')
            ->toArray();

        $bar = $this->output->createProgressBar($this->productsCount);
        $bar->start();

        $chunkSize = 100;
        for ($i = 0; $i < $this->productsCount; $i += $chunkSize) {
            $currentChunk = min($chunkSize, $this->productsCount - $i);
            for ($j = 0; $j < $currentChunk; $j++) {
                $this->createSingleProduct($attrIds, $variantCompatibleAttrIds, $attrByCode);
                $bar->advance();
            }
            // Очищаем накопленные события и память
            SiteContent::flushEventListeners();
            DB::flushQueryLog();
            gc_collect_cycles();
        }

        $bar->finish();
        $this->info("\nСоздано товаров: {$this->productsCount}");
    }

    protected function createSingleProduct(array $allAttrIds, array $variantCompatibleAttrIds, array $attrByCode): void
    {
        // --- Товар ---
        $catId = $this->categoryIds[array_rand($this->categoryIds)];
        $title = 'Товар ' . Str::random(8);
        $product = new SiteContent([
            'type'          => 'document',
            'contentType'   => 'text/html',
            'pagetitle'     => $title,
            'longtitle'     => $title,
            'description'   => '',
            'alias'         => Str::slug($title) . '-' . Str::random(4),
            'published'     => 1,
            'parent'        => $catId,
            'isfolder'      => 0,
            'template'      => 3,
            'menuindex'     => 0,
            'deleted'       => 0,
            'content'       => '<p>Описание товара</p>',
            'hide_from_tree' => 0,
            'privateweb'    => 0,
            'privatemgr'    => 0,
            'content_dispo' => 0,
            'hidemenu'      => 0,
            'alias_visible' => 1,
        ]);
        $product->save();
        $productId = $product->getKey();

        // --- Обычные атрибуты товара (product_attributes) ---
        $selectedAttrs = $this->randomUniqueIds($allAttrIds, $this->attributesPerProduct);
        $productAttrRows = [];
        foreach ($selectedAttrs as $attrId) {
            $productAttrRows[] = [
                'product_id'   => $productId,
                'attribute_id' => $attrId,
                'value'        => $this->randomValue($attrId),
            ];
        }
        DB::table('product_attributes')->insert($productAttrRows);

        // --- Варианты (с вероятностью variantProbability) ---
        if (mt_rand(1, 100) <= $this->variantProbability) {
            // Выбираем 2–3 вариантных атрибута, которые не конфликтуют с уже назначенными обычными,
            // но для простоты допустим пересечение (в реальном кейсе могут быть и там, и там разные назначения)
            $numVariantAttrs = mt_rand(2, 3);
            $variantAttrs = $this->randomUniqueIds($variantCompatibleAttrIds, $numVariantAttrs);

            // Сохраняем product_variant_attributes
            $pvaRows = [];
            foreach ($variantAttrs as $attrId) {
                $pvaRows[] = [
                    'product_id'   => $productId,
                    'attribute_id' => $attrId,
                ];
            }
            DB::table('product_variant_attributes')->insert($pvaRows);

            // Генерируем варианты
            $numVariants = mt_rand(2, $this->maxVariants);
            for ($v = 0; $v < $numVariants; $v++) {
                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id' => $productId,
                    'sort'       => $v,
                    'active'     => true,
                    'attrs_json' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Значения вариантных атрибутов
                $variantValues = [];
                $attrJson = [];
                foreach ($variantAttrs as $attrId) {
                    $value = $this->randomValue($attrId);
                    $variantValues[] = [
                        'variant_id'    => $variantId,
                        'attribute_id'  => $attrId,
                        'value'         => $value,
                        'value_numeric' => is_numeric($value) ? (float)$value : null,
                    ];
                    $attrJson[DB::table('attributes')->where('id', $attrId)->value('code')] = $value;
                }
                DB::table('variant_attribute_values')->insert($variantValues);

                // Обновим attrs_json
                DB::table('product_variants')->where('id', $variantId)->update([
                    'attrs_json' => json_encode($attrJson, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        // --- Пресеты (1-2 случайных) ---
        $presetCount = mt_rand(1, $this->maxPresetsPerProduct);
        $selectedPresets = (array)array_rand(array_flip($this->presetIds), min($presetCount, count($this->presetIds)));
        $presetRows = [];
        foreach ($selectedPresets as $presetId) {
            $presetRows[] = [
                'product_id' => $productId,
                'preset_id'  => $presetId,
                'applied_at' => now(),
            ];
        }
        DB::table('product_presets')->insert($presetRows);
    }

    // ========== Вспомогательные методы ==========

    protected function randomUniqueIds(array $pool, int $count): array
    {
        $count = min($count, count($pool));
        $keys = (array)array_rand(array_flip($pool), $count);
        return $keys;
    }

    protected function randomValue(int $attributeId): string
    {
        $attr = DB::table('attributes')->find($attributeId);
        if (!$attr) return '';

        if ($attr->field_type === 'select' && $attr->options) {
            $options = json_decode($attr->options, true);
            if (is_array($options) && count($options)) {
                return $options[array_rand($options)];
            }
        }

        // Для текстовых полей – случайные данные
        $randomTexts = [
            'weight'  => mt_rand(100, 5000) . ' г',
            'width'   => mt_rand(10, 200) . ' см',
            'height'  => mt_rand(5, 150) . ' см',
            'length'  => mt_rand(15, 300) . ' см',
            'power'   => mt_rand(5, 2000) . ' Вт',
            'voltage' => mt_rand(110, 240) . ' В',
            'capacity' => mt_rand(500, 10000) . ' мАч',
            'warranty' => mt_rand(1, 5) . ' года',
            'country' => collect(['Россия', 'Китай', 'Германия', 'США'])->random(),
            'sku'     => strtoupper(Str::random(8)),
            'packaging' => collect(['Коробка', 'Пакет', 'Блистер'])->random(),
        ];

        return $randomTexts[$attr->code] ?? ('Value ' . Str::random(6));
    }
}
