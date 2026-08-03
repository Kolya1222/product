# Evolution CMS Product & Variants Package

Высокопроизводительное, API-ориентированное решение для создания каталогов товаров с поддержкой вариантов (торговых предложений) и фасетного поиска (Faceted Search) для Evolution CMS.

Пакет спроектирован по паттернам EAV (Entity-Attribute-Value) с оптимизациями для MySQL (разделение текстовых и числовых значений, кэширование в JSON), что позволяет строить сложные фильтры без использования Elasticsearch и выдерживать каталоги от 100 000+ товаров.

---

## Использование (Backend)

### Базовый вывод каталога (Контроллер)

Для вывода каталога используйте `ProductFilterService`. Сервис сам определит типы полей и подберет оператор фильтрации (`IN` для списков, `BETWEEN` для чисел, `LIKE` для текста).

```php
namespace EvolutionCMS\Shop\Controllers;

use EvolutionCMS\Product\Services\ProductFilterService;
use EvolutionCMS\Product\Models\Attribute;

class CatalogController extends BaseController
{
    public function process()
    {
        parent::process();
        $filterService = app(ProductFilterService::class);
        $catalogId = $this->id;
        $depth = 3; // Поиск в подкатегориях до 3 уровня

        // 1. Получаем все атрибуты, которые есть в этой категории
        $allAttributes = $filterService->getAttributesForCatalog($catalogId, $depth);

        // 2. Получаем пагинированный список товаров с уже прикрепленными атрибутами
        $productsPaginator = $filterService->getFilteredProductsWithAttributes(
            $catalogId,
            request('filters', []),       // Активные фильтры из URL
            12,                            // Товаров на страницу
            request('sort', 'menuindex:asc'),
            array_column($allAttributes, 'code'), // Какие атрибуты вытащить в JSON
            null,
            [],
            $depth
        );

        // Сохраняем фильтры в ссылках пагинации
        $productsPaginator->appends(request()->except('page'));
        $productsPaginator->appends('depth', $depth);

        // 3. Передаем в Blade-шаблон
        $this->addViewData([
            'filterState' => $filterService->getFilterStateLight($allAttributes),
            'products'    => $productsPaginator,
            'catalogId'   => $catalogId,
            'depth'       => $depth
        ]);
    }
}
```

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

## AJAX Фильтрация (Frontend API)

Пакет предоставляет готовый контроллер `CatalogFilterController` для обработки AJAX-запросов. Маршруты регистрируются автоматически:

*   `GET /catalog/{catalogId}/filter` — Возвращает отфильтрованные товары (JSON) для пагинации.
*   `GET /catalog/{catalogId}/filter-state` — Возвращает состояние счетчиков фильтров.

### Пример интеграции в Blade-шаблоне

Форма фильтров в вашем `catalog.blade.php` должна содержать скрытое поле `depth`:

```blade
<form id="filterForm" method="GET" action="{{ request()->url() }}">
    <input type="hidden" name="depth" value="{{ $depth ?? 3 }}">
    
    <!-- Поля фильтров -->
    @foreach ($filterState as $attr)
        <div class="filter-section" data-code="{{ $attr['code'] }}">
            <div class="filter-section-title">{{ $attr['name'] }}</div>
            <div class="filter-options">
                @foreach ($attr['values'] as $val)
                    <label>
                        <input type="checkbox" name="filters[{{ $attr['code'] }}][]" value="{{ $val['value'] }}">
                        {{ $val['value'] }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</form>
```

JS-скрипт (чистый JS, без зависимостей) для обработки фильтров и пагинации:

```javascript
const filterForm = document.getElementById('filterForm');
const filterUrl = `/catalog/${catalogId}/filter`;
const filterStateUrl = `/catalog/${catalogId}/filter-state`;

// Сборка фильтров в объект
function getCurrentFilters() {
    const formData = new FormData(filterForm);
    const filters = {};
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('filters[')) {
            const match = key.match(/filters\[(.+?)\](\[\])?/);
            if (!match) continue;
            const attrCode = match[1];
            const isArray = match[2] !== undefined;
            if (isArray) {
                if (!filters[attrCode]) filters[attrCode] = [];
                filters[attrCode].push(value);
            } else {
                filters[attrCode] = value;
            }
        }
    }
    return filters;
}

// Основная функция AJAX-фильтрации
async function submitForm() {
    const formData = new FormData(filterForm);
    const params = new URLSearchParams(formData).toString();

    const [productsResponse] = await Promise.all([
        fetch(`${filterUrl}?${params}`).then(res => res.json()),
        refreshFilterState() // Обновляем счетчики
    ]);

    if (productsResponse.success) {
        renderProducts(productsResponse.items);
        renderPagination(productsResponse.pagination);
    }
}

// При клике на пагинацию
paginationContainer.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (!link) return;
    e.preventDefault();
    
    // Берем готовую ссылку (сервер уже добавил туда фильтры и depth)
    const hrefParams = link.href.split('?')[1] || '';
    const fetchUrl = `${filterUrl}?${hrefParams}`;
    
    fetch(fetchUrl)
        .then(res => res.json())
        .then(data => {
            renderProducts(data.items);
            renderPagination(data.pagination);
            window.history.pushState({}, '', currentPath + '?' + hrefParams);
        });
});
```

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