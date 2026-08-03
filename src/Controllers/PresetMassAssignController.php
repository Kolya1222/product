<?php

namespace roilafx\Product\Controllers;

use EvolutionCMS\TemplateController;
use roilafx\Product\Models\AttributePreset;
use roilafx\Product\Services\AttributePresetService;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;

class PresetMassAssignController extends TemplateController
{
    private AttributePresetService $presetService;

    public function __construct()
    {
        $this->presetService = new AttributePresetService();
    }

    public function form()
    {
        $presets = $this->presetService->getAll();
        $rootIds = \EvolutionCMS\Models\SiteContent::where('parent', 0)->pluck('id');

        $resources = \EvolutionCMS\Models\SiteContent::select(
            'site_content.id',
            'site_content.pagetitle',
            'site_content.parent',
            'site_content.isfolder',
            'site_content_closure.depth as level'
        )
            ->join('site_content_closure', 'site_content.id', '=', 'site_content_closure.descendant')
            ->whereIn('site_content_closure.ancestor', $rootIds)
            ->where('site_content_closure.depth', '>=', 0)
            ->orderBy('site_content_closure.ancestor')
            ->orderBy('site_content_closure.depth')
            ->orderBy('site_content.parent')
            ->orderBy('site_content.menuindex')
            ->distinct()
            ->get()
            ->toArray();

        $this->setView('products::presets.mass_assign');
        $this->addViewData([
            'presets'   => $presets,
            'resources' => $resources,
            'title'     => 'Массовое назначение пресетов',
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function store(Request $request)
    {
        $presetId = $request->input('preset_id');
        $mode = $request->input('mode', 'replace');
        $includeChildren = $request->has('include_children');
        $onlyChildren = $request->has('only_children');
        $resourceIds = $request->input('resource_ids', []);

        if (empty($presetId) || empty($resourceIds)) {
            return redirect()->back()->with('error', 'Выберите пресет и ресурсы');
        }

        $preset = AttributePreset::findOrFail($presetId);

        $processed = 0;
        $finalIds = [];
        if ($includeChildren || $onlyChildren) {
            $parentIds = array_unique($resourceIds);
            $children = SiteContent::select('site_content.id')
                ->join('site_content_closure', 'site_content.id', '=', 'site_content_closure.descendant')
                ->whereIn('site_content_closure.ancestor', $parentIds)
                ->where('site_content_closure.depth', '>', 0)
                ->pluck('id')
                ->toArray();

            if ($onlyChildren) {
                $finalIds = $children;
            } else {
                $finalIds = array_merge($parentIds, $children);
            }
        } else {
            $finalIds = $resourceIds;
        }
        $finalIds = array_unique($finalIds);

        foreach ($finalIds as $productId) {
            $this->presetService->applyToProduct(
                $productId,
                $preset,
                $mode
            );
            $processed++;
        }

        return redirect()->route('presets.module.index')
            ->with('success', "Пресет применён к {$processed} ресурсам.");
    }

    public function children(Request $request)
    {
        $parentId = $request->input('id', 0);
        $resources = \EvolutionCMS\Models\SiteContent::select(
            'site_content.id',
            'site_content.pagetitle as text',
            'site_content.isfolder as children'
        )
            ->where('parent', $parentId)
            ->orderBy('menuindex')
            ->get()
            ->map(function ($item) {
                $item->children = (bool) $item->children;
                $item->state = ['opened' => false];
                return $item;
            });

        return response()->json($resources);
    }
}
