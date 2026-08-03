# Примеры использования

Данный раздел содержит расширенные примеры работы с пакетом на уровне Backend (PHP) и Frontend (Blade + JS). Примеры охватывают как базовые сценарии, так и сложные кейсы интеграции.

---

## Оглавление

1. [Работа с атрибутами (AttributeService)](#1-работа-с-атрибутами-attributeservice)
2. [Управление вариантами товаров (ProductVariantService)](#2-управление-вариантами-товаров-productvariantservice)
3. [Вывод каталога и Фильтрация (ProductFilterService)](#3-вывод-каталога-и-фильтрация-productfilterservice)
4. [Пресеты и массовое назначение (AttributePresetService)](#4-пресеты-и-массовое-назначение-attributepresetservice)
5. [Использование Facades в шаблонах](#5-использование-facades-в-шаблонах)
6. [Интеграция Frontend (Blade + AJAX)](#6-интеграция-frontend-blade--ajax)

---

## 1. Работа с атрибутами (AttributeService)

Сервис `AttributeService` отвечает за создание атрибутов, их категоризацию и назначение товарам.

### Создание нового атрибута с привязкой к товару

Если атрибут создается в контексте товара, его можно сразу назначить:

```php
use EvolutionCMS\Product\Services\AttributeService;

$attributeService = app(AttributeService::class);

$attribute = $attributeService->createAttribute(
    [
        'name'       => 'Мощность',
        'code'       => 'power',
        'field_type' => 'number',
        'options'    => null,
        'category_id' => 1,
    ],
    $productId = 15 // Сразу назначим товару
);
```

### Групповое назначение/снятие атрибутов с товара

Метод `assignAttributesToProduct` использует diff-логику: он удалит неотмеченные атрибуты и добавит новые, не трогая существующие значения вариантов.

```php
$attributeService->assignAttributesToProduct(15, [1, 3, 5]); // ID атрибутов
```

### Получение атрибутов, сгруппированных по категориям

Идеально для рендеринга админ-панели:

```php
$grouped = $attributeService->getGroupedAttributesByProduct(15);
// Результат:
// [
//   ['category' => [...], 'attributes' => [...]],
//   ['category' => ['id' => 0, 'name' => 'Без категории'], 'attributes' => [...]]
// ]
```

---

## 2. Управление вариантами товаров (ProductVariantService)

Сервис инкапсулирует логику работы с EAV и JSON-кэшем варианта.

### Полный цикл создания варианта

При создании варианта сервис проверяет, разрешен ли этот атрибут для товара, сохраняет значения в БД и автоматически обновляет `attrs_json`.

```php
use EvolutionCMS\Product\Services\ProductVariantService;

$variantService = app(ProductVariantService::class);

try {
    $variant = $variantService->createVariant(15, [
        'color' => 'Черный',
        'size'  => 'L',
        'price' => 1500
    ]);
} catch (\InvalidArgumentException $e) {
    echo "Ошибка: " . $e->getMessage(); 
    // Например: "Некоторые атрибуты не назначены товару."
}
```

### Обновление значений варианта

Метод `updateVariant` полностью синхронизирует переданные значения. Если вы не передадите какой-то атрибут, он будет удален из варианта.

```php
$variant = \EvolutionCMS\Product\Models\ProductVariant::find(1);

$variantService->updateVariant($variant, [
    'color' => 'Белый',
    'price' => 1600
    // 'size' будет удален
]);
```

---

## 3. Вывод каталога и Фильтрация (ProductFilterService)

Ядро пакета, отвечающее за фасетный поиск.

### Базовый вывод с фильтрами

```php
$filterService = app(\EvolutionCMS\Product\Services\ProductFilterService::class);

$catalogId = 2;
$depth = 3;

$allAttributes = $filterService->getAttributesForCatalog($catalogId, $depth);

$paginator = $filterService->getFilteredProductsWithAttributes(
    $catalogId,
    request('filters', []),
    12,
    request('sort', 'menuindex:asc'),
    array_column($allAttributes, 'code'),
    null,
    [],
    $depth
);

$paginator->appends(request()->except('page'));
```

### Расширенная конфигурация фильтров (Filter Config)

Если стандартного автоопределения типов не хватает, используем конфиг:

```php
$filterConfig = [
    'weight' => [
        // Меняем отображение в админке/на фронте
        'display' => ['type' => 'range'], 
        // Заставляем искать по строгому совпадению (eq) вместо LIKE
        'filter'  => ['operator' => 'eq', 'field' => 'value_numeric'],
    ],
    'title' => [
        'filter' => ['operator' => 'like-l', 'field' => 'value'] // LIKE 'value%'
    ]
];

$paginator = $filterService->getCachedFilteredProducts(
    $catalogId,
    request('filters', []),
    12,
    'menuindex:asc',
    ['weight', 'title'],
    null,
    $filterConfig,
    $depth
);
```

### Ручной сброс кэша фильтров

Кэш сбрасывается через Observers автоматически, но если вы массово меняли товары через SQL:

```php
$filterService->clearFilterCache($catalogId);
```

---

## 4. Пресеты и массовое назначение (AttributePresetService)

Пресеты позволяют применять наборы атрибутов к товарам.

### Создание пресета

```php
$presetService = app(\EvolutionCMS\Product\Services\AttributePresetService::class);

$preset = $presetService->create([
    'name' => 'Характеристики ноутбука',
    'description' => 'Базовый набор для техники',
    'attributes' => [
        ['attribute_id' => 5, 'sort' => 0], // CPU
        ['attribute_id' => 6, 'sort' => 1], // RAM
    ]
]);
```

### Применение пресета к товарам (Массовое назначение)

В контроллере массового назначения (`PresetMassAssignController`) используется метод `applyToProduct`. Он поддерживает два режима: `replace` (удалить старые атрибуты товара и поставить из пресета) и `add` (добавить к существующим).

```php
// Применяем пресет ID=1 к товару ID=50 в режиме замены
$presetService->applyToProduct(50, \EvolutionCMS\Product\Models\AttributePreset::find(1), 'replace');
```

---

## 5. Использование Facades в шаблонах

Для удобства в Blade-шаблонах можно использовать Facades.

### Вывод вариантов товара в карточке (Frontend)

В карточке товара (SiteTemplate) подключите:
```blade
@php
    $variants = \EvolutionCMS\Product\Facades\ProductData::getVariants($product->id);
@endphp

@if($variants->isNotEmpty())
    <div class="product-variants">
        @foreach($variants as $variant)
            <button data-variant-id="{{ $variant->id }}">
                @foreach($variant->attrs as $code => $val)
                    {{ $attrNames[$code] ?? $code }}: {{ $val }}<br>
                @endforeach
            </button>
        @endforeach
    </div>
@endif
```

---

## 6. Интеграция Frontend (Blade + AJAX)

### Форма фильтров (Blade)

Важно: скрытое поле `depth` обязательно, если каталог использует вложенность.

```blade
<form id="filterForm" method="GET" action="{{ request()->url() }}">
    <input type="hidden" name="depth" value="{{ $depth ?? 0 }}">
    <input type="hidden" name="sort" value="{{ request('sort', 'menuindex:asc') }}">

    @foreach($filterState as $attr)
        <div class="filter-section" data-code="{{ $attr['code'] }}">
            <h3>{{ $attr['name'] }}</h3>
            
            @if($attr['type'] === 'number')
                <input type="number" name="filters[{{ $attr['code'] }}][min]" placeholder="от {{ $attr['min'] ?? '' }}">
                <input type="number" name="filters[{{ $attr['code'] }}][max]" placeholder="до {{ $attr['max'] ?? '' }}">
            @elseif(in_array($attr['type'], ['select', 'dropdown', 'checkbox']))
                @foreach($attr['values'] as $val)
                    <label>
                        <input type="checkbox" name="filters[{{ $attr['code'] }}][]" value="{{ $val['value'] }}">
                        {{ $val['value'] }}
                        <span class="count" data-count-for="{{ $val['value'] }}"></span>
                    </label>
                @endforeach
            @else
                <input type="text" name="filters[{{ $attr['code'] }}]" placeholder="Поиск...">
            @endif
        </div>
    @endforeach
</form>
```

### JavaScript: Обработка AJAX-фильтрации и пагинации

Пример чистого JS (Vanilla) скрипта, который обрабатывает форму, запрашивает товары и обновляет счетчики.

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    if (!form) return;

    const grid = document.getElementById('productsGrid');
    const pagination = document.getElementById('paginationContainer');
    const catalogId = window.catalogId;
    const depthInput = form.querySelector('input[name="depth"]');

    const filterApiUrl = `/catalog/${catalogId}/filter`;
    const stateApiUrl = `/catalog/${catalogId}/filter-state`;

    // Утилита: собрать параметры
    function getParams() {
        return new URLSearchParams(new FormData(form)).toString();
    }

    // Утилита: получить только активные фильтры для state-запроса
    function getFiltersObject() {
        const data = {};
        new FormData(form).forEach((value, key) => {
            if (key.startsWith('filters[')) {
                // Грубый парсинг, для точного используйте регулярки из основного скрипта
                let match = key.match(/filters\[(.+?)\](\[\])?/);
                if (match) {
                    let code = match[1];
                    if (match[2]) {
                        if (!data[code]) data[code] = [];
                        data[code].push(value);
                    } else {
                        data[code] = value;
                    }
                }
            }
        });
        return data;
    }

    // Запрос товаров
    async function fetchProducts() {
        grid.style.opacity = 0.5;
        const params = getParams();
        
        // Обновляем URL
        window.history.pushState({}, '', `${window.location.pathname}?${params}`);

        try {
            const res = await fetch(`${filterApiUrl}?${params}`);
            const data = await res.json();
            
            if (data.success) {
                renderProducts(data.items);
                renderPagination(data.pagination);
            }
        } catch (e) {
            console.error(e);
        } finally {
            grid.style.opacity = 1;
        }
    }

    // Запрос счетчиков
    async function fetchState() {
        const filters = getFiltersObject();
        const params = new URLSearchParams();
        
        for (const [code, value] of Object.entries(filters)) {
            if (Array.isArray(value)) {
                value.forEach(v => params.append(`filters[${code}][]`, v));
            } else {
                params.append(`filters[${code}]`, value);
            }
        }
        if (depthInput) params.append('depth', depthInput.value);

        try {
            const res = await fetch(`${stateApiUrl}?${params.toString()}`);
            const data = await res.json();
            
            if (data.success) {
                updateCounters(data.state);
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Обновление DOM счетчиками
    function updateCounters(state) {
        state.forEach(attr => {
            if (!attr.values) return;
            attr.values.forEach(val => {
                const label = form.querySelector(`input[value="${val.value}"]`).closest('label');
                const countSpan = label.querySelector('.count');
                
                if (countSpan) countSpan.textContent = val.count;
                
                if (val.available) {
                    label.classList.remove('disabled');
                    label.querySelector('input').disabled = false;
                } else {
                    label.classList.add('disabled');
                    label.querySelector('input').disabled = true;
                }
            });
        });
    }

    // Рендер пагинации (простой пример)
    function renderPagination(p) {
        if (!p || p.last_page <= 1) {
            pagination.innerHTML = '';
            return;
        }
        let html = '';
        for (let i = 1; i <= p.last_page; i++) {
            html += `<a href="?page=${i}" class="pagination-item ${i === p.current_page ? 'active' : ''}">${i}</a>`;
        }
        pagination.innerHTML = html;
    }

    // События
    form.addEventListener('change', () => {
        fetchProducts();
        fetchState();
    });

    pagination.addEventListener('click', (e) => {
        if (e.target.matches('.pagination-item')) {
            e.preventDefault();
            const page = new URLSearchParams(e.target.href.split('?')[1]).get('page');
            form.querySelector('input[name="page"]').value = page;
            fetchProducts();
        }
    });
});
```