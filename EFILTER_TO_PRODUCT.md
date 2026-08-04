# Миграция каталога интернет-магазина (Shop) на новый фильтр (roilafx/product)

## Шаг 1. Обновление Контроллера каталога

Раньше контроллер делегировал работу абстрактному `FilterServiceInterface` и возвращал готовый HTML. Теперь мы используем фасад `ProductFilter` и передаем в шаблон только данные (массивы/объекты).

Замените содержимое вашего `CatalogController.php` на следующее:

```php
<?php

namespace EvolutionCMS\Shop\Controllers;

use EvolutionCMS\TemplateController;
use roilafx\Product\Facades\ProductFilter;

class CatalogController extends TemplateController
{  
    public function process()
    {
        $catalogId = $this->id;
        $depth = 3; // Глубина поиска в подкатегориях
        $activeFilters = request('filters', []);

        // 1. Получаем все атрибуты, доступные в этой категории
        $allAttributes = ProductFilter::getAttributesForCatalog($catalogId, $depth);

        // 2. Собираем словарь имен атрибутов (для вывода в карточках товаров)
        $attrNames = array_column($allAttributes, 'name', 'code');

        // 3. Формируем состояние фильтров для первоначального рендера
        $filterState = ProductFilter::getFilterStateLight($allAttributes, $activeFilters);

        // 4. Получаем товары с учетом URL-фильтров
        $productsPaginator = ProductFilter::getFilteredProductsWithAttributes(
            $catalogId,
            $activeFilters,
            12, // Товаров на страницу
            request('sort', 'menuindex:asc'),
            array_column($allAttributes, 'code'),
            null,
            [],
            $depth
        );

        // Сохраняем параметры в ссылки пагинации
        $productsPaginator->appends(request()->except('page'));
        $productsPaginator->appends('depth', $depth);

        // 5. Передаем данные в шаблон массивом
        $this->addViewData([
            'filterState' => $filterState,
            'products'    => $productsPaginator,
            'attrNames'   => $attrNames,
            'catalogId'   => $catalogId,
            'depth'       => $depth
        ]);
    }
}
```

---

## Шаг 2. Обновление шаблона каталога (`catalog.blade.php`)

Старый шаблон просто выводил `{!! $filter !!}`. Теперь мы сами рисуем форму фильтров на Blade, а для AJAX-обновления используем нативный JS. 

Полностью замените ваш `catalog.blade.php` на этот код:

```blade
@extends('layout.app')

@section('styles')
    <style>
        /* Стили каталога */
    </style>
@endsection

@section('content')
    {!! $breadcrumbs ?? '' !!}
    <div class="container">
        <button class="filter-mobile-btn" onclick="document.querySelector('.filter-sidebar').classList.toggle('show')">
            Показать фильтры
        </button>

        <div class="catalog-layout">
            <!-- ФОРМА ФИЛЬТРОВ -->
            <form id="filterForm" method="GET" action="{{ request()->url() }}">
                <input type="hidden" name="depth" value="{{ $depth ?? 3 }}">
                <div class="filter-sidebar" id="filterSidebar">
                    <div class="filter-header">
                        <h3>Фильтры</h3>
                        <a href="{{ request()->url() }}" class="filter-clear" id="filterClear">Сбросить</a>
                    </div>

                    <div id="filterFieldsContainer">
                        @foreach ($filterState as $attr)
                            <div class="filter-section" data-code="{{ $attr['code'] }}">
                                <div class="filter-section-title">
                                    <span>{{ $attr['name'] }}</span>
                                </div>

                                @if (in_array($attr['type'], ['number', 'range']))
                                    <div class="filter-range">
                                        <div class="filter-range-inputs">
                                            <input type="number" class="filter-range-input" name="filters[{{ $attr['code'] }}][min]" placeholder="от {{ $attr['min'] ?? '' }}" value="{{ $attr['current_min'] ?? '' }}">
                                            <input type="number" class="filter-range-input" name="filters[{{ $attr['code'] }}][max]" placeholder="до {{ $attr['max'] ?? '' }}" value="{{ $attr['current_max'] ?? '' }}">
                                        </div>
                                    </div>
                                @elseif (in_array($attr['type'], ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox']))
                                    <div class="filter-options" data-attr-code="{{ $attr['code'] }}">
                                        @foreach ($attr['values'] as $val)
                                            @php $checked = in_array($val['value'], (array) request('filters.' . $attr['code'])); @endphp
                                            <label class="filter-option">
                                                <input type="checkbox" name="filters[{{ $attr['code'] }}][]" value="{{ $val['value'] }}" {{ $checked ? 'checked' : '' }}>
                                                {{ $val['value'] }}
                                                <span class="count"></span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <input type="text" class="filter-range-input" name="filters[{{ $attr['code'] }}]" value="{{ $attr['current_value'] ?? '' }}" placeholder="Поиск...">
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <input type="hidden" name="page" value="{{ request('page', 1) }}">
                    <button type="submit" class="filter-apply-btn">Применить</button>
                </div>
            </form>

            <!-- ТОВАРЫ -->
            <div class="catalog-content">
                <div class="catalog-header">
                    <div class="catalog-title">
                        <h1>{{ $pagetitle }}</h1>
                        <p id="productsCount">Найдено: {{ $products->total() }}</p>
                    </div>
                </div>

                <div id="productsGrid" class="catalog-grid">
                    @foreach ($products as $product)
                        @include('parts.itemcard', ['item' => $product, 'attrNames' => $attrNames])
                    @endforeach
                </div>

                <!-- Пагинация -->
                <div id="paginationContainer">
                    @if ($products->hasPages())
                        <nav class="pagination">
                            @if ($products->onFirstPage())
                                <span class="pagination-item disabled">«</span>
                            @else
                                <a href="{{ $products->previousPageUrl() }}" class="pagination-item">«</a>
                            @endif
                            @for ($i = 1; $i <= $products->lastPage(); $i++)
                                @if ($i == $products->currentPage())
                                    <span class="pagination-item active">{{ $i }}</span>
                                @else
                                    <a href="{{ $products->url($i) }}" class="pagination-item">{{ $i }}</a>
                                @endif
                            @endfor
                            @if ($products->hasMorePages())
                                <a href="{{ $products->nextPageUrl() }}" class="pagination-item">»</a>
                            @else
                                <span class="pagination-item disabled">»</span>
                            @endif
                        </nav>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Шаблон для AJAX-рендеринга карточки (API-FIRST подход) -->
    <!-- Браузер не отображает этот тег, но JS может его клонировать при фильтрации -->
    <template id="product-card-template">
        <a href="#" title="" style="text-decoration: none">
            <div class="product-card">
                <div class="product-image"><i class="fas fa-laptop"></i></div>
                <div class="product-title"></div>
                <div class="product-attributes-container"></div>
            </div>
        </a>
    </template>
@endsection

@section('scripts')
    <script>
        window.catalogId = {{ $catalogId ?? 'null' }};
        window.attrNames = @json($attrNames);
        
        (function() {
            const catalogId = window.catalogId;
            if (!catalogId) return;

            const filterForm = document.getElementById('filterForm');
            const productsGrid = document.getElementById('productsGrid');
            const paginationContainer = document.getElementById('paginationContainer');
            const productsCount = document.getElementById('productsCount');
            const currentPath = window.location.pathname;
            const filterStateUrl = `/catalog/${catalogId}/filter-state`;
            const filterUrl = `/catalog/${catalogId}/filter`;

            // Утилиты для сбора формы
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

            function cleanFormData(formData) {
                const cleaned = new FormData();
                for (let [key, value] of formData.entries()) {
                    if (value === '' || value === null) continue;
                    cleaned.append(key, value);
                }
                if (!cleaned.has('page')) cleaned.set('page', '1');
                return cleaned;
            }

            // Обновление счетчиков в форме
            function updateFilterForm(filterState) {
                filterState.forEach(attr => {
                    const section = document.querySelector(`.filter-section[data-code="${attr.code}"]`);
                    if (!section) return;
                    if (['select', 'dropdown', 'checkbox'].includes(attr.type)) {
                        const optionsContainer = section.querySelector('.filter-options');
                        if (!optionsContainer || !attr.values) return;
                        const selectedValues = getCurrentFilters()[attr.code] || [];
                        let html = '';
                        attr.values.forEach(val => {
                            const isChecked = selectedValues.includes(val.value);
                            const disabled = !val.available;
                            html += `
                                <label class="filter-option ${disabled ? 'disabled' : ''}">
                                    <input type="checkbox" name="filters[${attr.code}][]" value="${val.value}" ${isChecked ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                                    ${val.value} <span class="count">${val.count !== undefined ? val.count : ''}</span>
                                </label>`;
                        });
                        optionsContainer.innerHTML = html;
                    }
                });
            }

            async function refreshFilterState() {
                const filters = getCurrentFilters();
                const params = new URLSearchParams();
                for (const [code, value] of Object.entries(filters)) {
                    if (Array.isArray(value)) value.forEach(v => params.append(`filters[${code}][]`, v));
                    else params.set(`filters[${code}]`, value);
                }
                const depthInput = document.querySelector('input[name="depth"]');
                if (depthInput) params.set('depth', depthInput.value);

                try {
                    const response = await fetch(`${filterStateUrl}?${params.toString()}`);
                    const data = await response.json();
                    if (data.success) updateFilterForm(data.state);
                } catch (error) { console.error('Ошибка filter-state:', error); }
            }

            // Рендер товаров из <template> по данным JSON
            function renderProducts(items) {
                const template = document.getElementById('product-card-template');
                productsGrid.innerHTML = '';
                items.forEach(item => {
                    const clone = template.content.cloneNode(true);
                    const link = clone.querySelector('a');
                    const titleEl = clone.querySelector('.product-title');
                    const attrsContainer = clone.querySelector('.product-attributes-container');

                    link.href = item.url || '#';
                    link.title = item.title;
                    titleEl.textContent = item.title;

                    let attrsHtml = '';
                    for (const [code, value] of Object.entries(item.attrs || {})) {
                        if (!value) continue;
                        const name = window.attrNames[code] || code;
                        attrsHtml += `<div class="product-attribute"><span class="attr-name">${name}:</span> ${value}</div>`;
                    }
                    attrsContainer.innerHTML = attrsHtml;
                    productsGrid.appendChild(clone);
                });
            }

            function renderPagination(pagination) {
                if (!pagination || pagination.last_page <= 1) { paginationContainer.innerHTML = ''; return; }
                let html = '<nav class="pagination">';
                const baseParams = new URLSearchParams(cleanFormData(new FormData(filterForm)));
                baseParams.delete('page');
                
                const makeUrl = (page) => '?' + new URLSearchParams(baseParams).set('page', page).toString();

                html += pagination.current_page > 1 ? `<a href="${makeUrl(pagination.current_page - 1)}" class="pagination-item">«</a>` : `<span class="pagination-item disabled">«</span>`;
                for (let i = 1; i <= pagination.last_page; i++) {
                    html += i === pagination.current_page ? `<span class="pagination-item active">${i}</span>` : `<a href="${makeUrl(i)}" class="pagination-item">${i}</a>`;
                }
                html += pagination.current_page < pagination.last_page ? `<a href="${makeUrl(pagination.current_page + 1)}" class="pagination-item">»</a>` : `<span class="pagination-item disabled">»</span>`;
                
                paginationContainer.innerHTML = html + '</nav>';
            }

            async function submitForm() {
                const formData = new FormData(filterForm);
                const cleaned = cleanFormData(formData);
                const params = new URLSearchParams(cleaned).toString();

                window.history.pushState({}, '', currentPath + '?' + params);
                productsGrid.style.opacity = '0.5';

                try {
                    const [productsResponse] = await Promise.all([
                        fetch(`${filterUrl}?${params}`).then(res => res.json()),
                        refreshFilterState()
                    ]);
                    if (productsResponse.success) {
                        renderProducts(productsResponse.items);
                        renderPagination(productsResponse.pagination);
                        productsCount.textContent = 'Найдено: ' + productsResponse.pagination.total;
                    }
                } catch (error) { console.error('Ошибка:', error); } 
                finally { productsGrid.style.opacity = '1'; }
            }

            filterForm.addEventListener('change', (e) => {
                if (e.target.type === 'hidden') return;
                clearTimeout(filterForm.submitTimer);
                filterForm.submitTimer = setTimeout(() => filterForm.requestSubmit(), 200);
            });

            filterForm.addEventListener('submit', (e) => { e.preventDefault(); submitForm(); });

            paginationContainer.addEventListener('click', function(e) {
                const link = e.target.closest('a');
                if (!link) return;
                e.preventDefault();
                const hrefParams = link.href.split('?')[1] || '';
                fetch(`${filterUrl}?${hrefParams}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            renderProducts(data.items);
                            renderPagination(data.pagination);
                            productsCount.textContent = 'Найдено: ' + data.pagination.total;
                            window.history.pushState({}, '', currentPath + '?' + hrefParams);
                            refreshFilterState();
                        }
                    });
            });

            // Первичная загрузка счетчиков
            refreshFilterState();
        })();
    </script>
@endsection
```

---

## Шаг 3. Обновление карточки товара (`parts/itemcard.blade.php`)

Замените содержимое `itemcard.blade.php`:

```blade
@php
    // Безопасно извлекаем данные из объекта товара
    $attrs = $item->attrs ?? [];
    
    // Если цена или теги хранятся как атрибуты, достаем их из JSON
    $price = $attrs['price'] ?? null;
    $product_tag = $attrs['product_tag'] ?? null;
@endphp

<a href="@makeUrl($item->id)" title="{{ $item->pagetitle }}" style="text-decoration: none">
    <div class="product-card">
        @if ($product_tag)
            <span class="product-tag">{{ $product_tag }}</span>
        @endif
        
        <div class="product-image">
            <i class="fas fa-laptop"></i>
        </div>
        <div class="product-category">{{ $item->category_name ?? '' }}</div>
        
        <div class="product-title">{{ $item->pagetitle }}</div>

        @foreach($attrs as $code => $value)
            @if($code !== 'price' && $code !== 'product_tag' && !empty($value))
                <div class="product-attribute">
                    <span class="attr-name">{{ $attrNames[$code] ?? $code }}:</span> {{ $value }}
                </div>
            @endif
        @endforeach

        @if ($price)
            <div class="product-footer">
                <span class="product-price">{{ $price }} ₽</span> 
                <button class="btn-circle" data-commerce-action="add" data-id="{{ $item->id }}" data-count="1">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        @endif
    </div>
</a>
```