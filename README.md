# Evolution CMS Product & Variants Package

API-ориентированное решение для создания каталогов товаров с поддержкой вариантов для Evolution CMS. 
Пересборка классических TV под коммерческую составляющую. Включает в себя фильтрацию, импорт, экспорт UI и CLI.
Основная логическая единица Атрибут (TV) разделенная на общие и вариативные.

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

### Вывод каталога и фильтрация

Для вывода товаров и формы фильтрации на сайте, используйте фасад ProductFilter. Рекомендуется использовать метод getCachedFilteredProducts, чтобы тяжелые запросы кэшировались.

```php
<?php

namespace EvolutionCMS\Shop\Controllers;

use EvolutionCMS\Shop\Controllers\BaseController;
use roilafx\Product\Facades\ProductFilter;

class CatalogController extends BaseController
{  
    public function process()
    {
        parent::process();
        
        // 1. Базовые настройки каталога
        $catalogId = $this->id;
        $depth = 3; // Глубина поиска в подкатегориях
        $perPage = 6; // Товаров на страницу
        
        // 2. Получаем активные фильтры из URL (например, ?filters[color]=Красный)
        $activeFilters = request('filters', []);
        $sort = request('sort', 'menuindex:asc');

        // 3. Получаем все атрибуты, доступные в этой категории
        $allAttributes = ProductFilter::getAttributesForCatalog($catalogId, $depth);
        
        // 4. Собираем словарь имен атрибутов для вывода в карточках товаров
        $attrNames = array_column($allAttributes, 'name', 'code');

        // 5. Формируем состояние фильтров для первоначального рендера формы
        $filterState = ProductFilter::getFilterStateLight($allAttributes, $activeFilters);

        // 6. Получаем товары с кэшированием
        $productsPaginator = ProductFilter::getCachedFilteredProducts(
            $catalogId,
            $activeFilters,
            $perPage,
            $sort,
            array_column($allAttributes, 'code'),
            null,
            [],
            $depth
        );

        // 7. Сохраняем параметры в ссылки пагинации (чтобы фильтры не сбрасывались при переходе на 2-ю страницу)
        $productsPaginator->appends(request()->except('page'));
        $productsPaginator->appends('depth', $depth);

        // 8. Передаем данные в шаблон (Blade)
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

### Работа с вариантами товаров (ProductVariantService)

```php
use roilafx\Product\Services\ProductVariantService;

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


## CLI Инструменты для старта

В пакет включены Artisan-команды для тестирования производительности и обслуживания кэша:

- Генерация тестовых данных (10 категорий, 14 атрибутов, 4 пресета, 5000 товаров)
```bash
php artisan product:generate-test-data
```

- Принудительный прогрев кэша фильтров для всех категорий каталога
```bash
php artisan product:warm-cache
```
