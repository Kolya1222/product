# Evolution CMS Product & Variants Package

API-ориентированное решение для создания каталогов товаров с поддержкой общих характеристик, вариантов (торговых предложений) и высокопроизводительного фасетного поиска (Faceted Search) для Evolution CMS.

---

## Установка

1. Установка пакета
   ```bash
   php artisan package:installrequire roilafx/product "*"
   ```
2. Публикация стилей и скриптов
   ```bash
   php artisan vendor:publish --provider="roilafx\Product\ProductServiceProvider"
   ```
3. Выполните миграции:
   ```bash
   php artisan migrate
   ```

---

### Общие характеристики и Вариации

Пакет разделяет атрибуты товара на два типа:
1. **Общие характеристики (`product_attributes`)** - атрибуты, принадлежащие самому товару (Бренд, Страна, Базовая цена). Выводятся отдельным блоком в админ-панели и участвуют в фильтрации наравне с вариантами.
2. **Вариации (`product_variants`)** - торговые предложения (Цвет, Размер). У каждого варианта свой набор значений, которые хранятся в EAV и кэшируются в JSON-поле `attrs_json`.

### Кастомная конфигурация фильтров (Filter Config)

В 95% случаев сервис работает без конфигурации. Но если вам нужно переопределить логику (например, фильтровать текстовое поле строго по совпадению, или поменять тип отображения), передайте `$filterConfig`:

```php
$filterConfig = [
    'price' => [
        'display' => ['type' => 'number'],
        'filter'  => ['operator' => 'between', 'field' => 'value_numeric'],
    ],
    'brand' => [
        'display' => ['type' => 'select'],
        'filter'  => ['operator' => 'eq', 'field' => 'value'], // Точное совпадение вместо LIKE
    ],
    'weight' => [
        'filter' => ['operator' => 'gte'] // Больше или равно
    ]
];

$productsPaginator = $filterService->getCachedFilteredProducts(
    $catalogId,
    request('filters', []),
    12,
    'menuindex:asc',
    ['price', 'brand', 'weight'],
    null,
    $filterConfig,
    $depth
);
```

### Работа с вариантами товаров (ProductVariantService)

```php
use EvolutionCMS\Product\Services\ProductVariantService;

$variantService = app(ProductVariantService::class);

// Создание варианта для товара ID = 15
$variant = $variantService->createVariant(15, [
    'color' => 'Красный',
    'size'  => 'XL',
    'price' => 1999
]);

// Обновление варианта
$variantService->updateVariant($variant, [
    'price' => 1899
]);
```
*Сервис автоматически проверит, назначены ли эти атрибуты товару, сохранит значения в EAV и обновит `attrs_json`.*

---

## CLI Инструменты

В пакет включены Artisan-команды для тестирования производительности и обслуживания кэша:

```bash
# Генерация тестовых данных (10 категорий, 14 атрибутов, 4 пресета, 5000 товаров)
php artisan product:generate-test-data

# Принудительный прогрев кэша фильтров для всех категорий каталога
php artisan product:warm-cache
```

