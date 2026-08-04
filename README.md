# Evolution CMS Product & Variants Package

API-ориентированное решение для создания каталогов товаров с поддержкой вариантов (торговых предложений) и фасетного поиска (Faceted Search) для Evolution CMS.

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

$productsPaginator = $filterService->getFilteredProductsWithAttributes(
    $catalogId,
    request('filters', []),
    12,
    'menuindex:asc',
    ['price', 'brand', 'weight'],
    null,
    $filterConfig, // <--- Передаем конфиг
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

Для тестирования производительности в пакет включена Artisan-команда, генерирующая реалистичные данные:

```bash
php artisan product:generate-test-data
```
Команда создаст:
*   10 категорий и подкатегорий.
*   14 атрибутов (цвет, размер, вес, цена и т.д.).
*   4 пресета (Одежда, Электроника и т.д.).
*   5000 товаров с 2-6 вариантами у каждого.

---

## Производительность и Кэширование

*   **Кэширование фильтров:** Результаты `getFilterState` и `getFilteredProducts` кэшируются на 1 час (3600 сек). Ключ кэша включает ID категории, сериализованные фильтры и конфиг.
*   **Сброс кэша:** Кэш сбрасывается автоматически через Observers (`ProductVariantObserver`, `VariantAttributeValueObserver`) при любом изменении вариантов или их значений.
*   **Subqueries:** Все методы сервиса используют Closures в `whereIn`, что позволяет MySQL оптимизировать план выполнения запроса без передачи огромных массивов ID между PHP и БД.