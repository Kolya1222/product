<?php

namespace roilafx\Product\Console\Commands;

use Illuminate\Console\Command;
use roilafx\Product\Services\ProductFilterService;
use EvolutionCMS\Models\SiteContent;

class WarmCatalogCache extends Command
{
    protected $signature = 'product:warm-cache';
    protected $description = 'Прогревает кэш фильтров для всех категорий каталога';

    public function handle()
    {
        $this->info('Начинаем прогрев кэша каталога...');

        // Находим все папки-категории (isfolder=1)
        $categories = SiteContent::where('isfolder', 1)
            ->where('published', 1)
            ->where('deleted', 0)
            ->get();

        $filterService = app(ProductFilterService::class);
        $bar = $this->output->createProgressBar($categories->count());
        $bar->start();

        foreach ($categories as $category) {
            $depth = 3;

            // 1. Прогреваем атрибуты
            $allAttrs = $filterService->getAttributesForCatalog($category->id, $depth);

            // 2. Прогреваем легкое состояние (для первоначального вывода формы)
            $filterService->getFilterStateLight($allAttrs, []);

            // 3. ПРОГРЕВАЕМ ТЯЖЕЛЫЕ СЧЕТЧИКИ (Именно это дергает AJAX!)
            $filterService->getFilterState($category->id, [], [], $depth);

            // 4. Прогреваем товары
            $filterService->getCachedFilteredProducts(
                $category->id,
                [],
                12,
                'menuindex:asc',
                array_column($allAttrs, 'code'),
                null,
                [],
                $depth
            );

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nКэш каталога успешно прогрет!");
    }
}
