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

Сервис `AttributeService` отвечает за создание атрибутов, их категоризацию и назначение товарам (как для вариаций, так и общих характеристик).

### Создание нового атрибута с привязкой к товару

Если атрибут создается в контексте товара, его можно сразу назначить:

```php
use roilafx\Product\Services\AttributeService;

$attributeService = app(AttributeService::class);

$attribute = $attributeService->createAttribute(
    [
        'name'       => 'Мощность',
        'code'       => 'power',
        'field_type' => 'number',
        'options'    => null,
        'category_id' => 1,
    ],
    $productId = 15 // Сразу назначим товару как поле для вариаций
);
```

### Назначение атрибутов товару

Методы `assignAttributesToProduct` и `assignGeneralAttributesToProduct` используют diff-логику: они удалят неотмеченные атрибуты и добавят новые, не трогая существующие значения.

```php
// Назначить атрибуты для вариаций
$attributeService->assignAttributesToProduct(15, [1, 3, 5]); 

// Назначить общие характеристики (Бренд, Страна и т.д.)
$attributeService->assignGeneralAttributesToProduct(15, [7, 8]);
```

### Получение атрибутов, сгруппированных по категориям

Универсальный метод `getGroupedAttributesByProduct` принимает вторым аргументом тип атрибутов (`'variant'` или `'general'`). Для общих атрибутов он также вернет их текущие значения.

```php
// Получаем общие характеристики с их значениями
$groupedGeneral = $attributeService->getGroupedAttributesByProduct(15, 'general');

// Результат:
// [
//   ['category' => [...], 'attributes' => [
//      ['id' => 7, 'name' => 'Бренд', 'assigned' => true, 'value' => 'Apple'],
//      ...
//   ]]
// ]
```

---

## 2. Управление вариантами товаров (ProductVariantService)

Сервис инкапсулирует логику работы с EAV и JSON-кэшем варианта.

### Полный цикл создания варианта

При создании варианта сервис проверяет, разрешен ли этот атрибут для товара, сохраняет значения в БД и автоматически обновляет `attrs_json`.

```php
use roilafx\Product\Services\ProductVariantService;

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
$variant = \roilafx\Product\Models\ProductVariant::find(1);

$variantService->updateVariant($variant, [
    'color' => 'Белый',
    'price' => 1600
    // 'size' будет удален
]);
```

---

## 3. Вывод каталога и Фильтрация (ProductFilterService)

Ядро пакета, отвечающее за фасетный поиск. Оптимизировано для работы с 100 000+ товаров.

### Базовый вывод с фильтрами (с использованием кэша)

Рекомендуется всегда использовать метод `getCachedFilteredProducts`, который автоматически закэширует результат пагинации на 1 час.

```php
$filterService = app(\roilafx\Product\Services\ProductFilterService::class);

$catalogId = 2;
$depth = 3;

$allAttributes = $filterService->getAttributesForCatalog($catalogId, $depth);
$filterState = $filterService->getFilterStateLight($allAttributes, request('filters', []));

$paginator = $filterService->getCachedFilteredProducts(
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

Кэш сбрасывается через Observers автоматически (с учетом всех родительских категорий), но если вы массово меняли товары через SQL:

```php
$filterService->clearFilterCache($catalogId);
```

---

## 4. Пресеты и массовое назначение (AttributePresetService)

Пресеты позволяют применять наборы атрибутов к товарам. Поддерживается применение как к вариациям, так и к общим характеристикам.

### Создание пресета

```php
$presetService = app(\roilafx\Product\Services\AttributePresetService::class);

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

Метод `applyToProduct` поддерживает два режима: `replace` (удалить старые атрибуты товара и поставить из пресета) и `add` (добавить к существующим). Третьим параметром указывается `target` (`'variant'` или `'general'`).

```php
// Применяем пресет ID=1 к товару ID=50 в режиме замены для ОБЩИХ атрибутов
$presetService->applyToProduct(
    50, 
    \roilafx\Product\Models\AttributePreset::find(1), 
    'replace',
    'general'
);
```

---

## 5. Использование Facades в шаблонах

Для удобства в Blade-шаблонах можно использовать Facades.

### Вывод вариантов товара в карточке (Frontend)

В карточке товара (SiteTemplate) подключите:
```blade
@php
    $variants = \roilafx\Product\Facades\ProductData::getVariants($product->id);
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
            
            @if(in_array($attr['type'], ['number', 'range']))
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

Современный пример на чистом JS (Vanilla), использующий `Promise.all` для параллельного запроса товаров и счетчиков.

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

    // Сборка параметров формы
    function getParams() {
        return new URLSearchParams(new FormData(form)).toString();
    }

    // Запрос товаров и счетчиков
    async function applyFilters() {
        grid.style.opacity = 0.5;
        const params = getParams();
        
        // Обновляем URL без перезагрузки
        window.history.pushState({}, '', `${window.location.pathname}?${params}`);

        try {
            // Параллельно запрашиваем товары и состояние фильтров
            const [productsRes, stateRes] = await Promise.all([
                fetch(`${filterApiUrl}?${params}`).then(res => res.json()),
                fetch(`${stateApiUrl}?${params}`).then(res => res.json())
            ]);

            if (productsRes.success) {
                renderProducts(productsRes.items);
                renderPagination(productsRes.pagination);
            }
            if (stateRes.success) {
                updateCounters(stateRes.state);
            }
        } catch (e) {
            console.error(e);
        } finally {
            grid.style.opacity = 1;
        }
    }

    // Обновление DOM счетчиками
    function updateCounters(state) {
        state.forEach(attr => {
            if (!attr.values) return;
            attr.values.forEach(val => {
                const input = form.querySelector(`input[value="${val.value}"]`);
                if (!input) return;
                
                const label = input.closest('label');
                const countSpan = label.querySelector('.count');
                
                if (countSpan) countSpan.textContent = val.count;
                
                if (val.available) {
                    label.classList.remove('disabled');
                    input.disabled = false;
                } else {
                    label.classList.add('disabled');
                    input.disabled = true;
                }
            });
        });
    }

    // Рендер пагинации
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
    form.addEventListener('change', () => applyFilters());

    pagination.addEventListener('click', (e) => {
        if (e.target.matches('.pagination-item')) {
            e.preventDefault();
            const page = new URLSearchParams(e.target.href.split('?')[1]).get('page');
            form.querySelector('input[name="page"]')?.remove();
            
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'page';
            hiddenInput.value = page;
            form.appendChild(hiddenInput);
            
            applyFilters();
        }
    });
});
```