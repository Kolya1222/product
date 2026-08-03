<?php

namespace roilafx\Product\Controllers;

use EvolutionCMS\TemplateController;
use roilafx\Product\Models\AttributePreset;
use roilafx\Product\Services\AttributePresetService;
use roilafx\Product\Traits\ResolvesAttributes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class PresetController extends TemplateController
{
    use ResolvesAttributes;

    private AttributePresetService $presetService;

    public function __construct()
    {
        $this->presetService = new AttributePresetService();
    }

    public function index()
    {
        $this->setView('products::presets.index');
        $this->addViewData([
            'presets' => $this->presetService->getAll(),
            'title'   => 'Шаблоны атрибутов (пресеты)',
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function create()
    {
        $this->setView('products::presets.form');
        $this->addViewData([
            'preset'        => null,
            'allAttributes' => $this->getAllAttributesFlat(),
            'title'         => 'Новый пресет',
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:attribute_presets,name',
            'description' => 'nullable|string',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['name', 'description']);
        $data['attributes'] = $this->cleanAttributes($request->input('attributes', []));
        $this->presetService->create($data);
        return redirect()->route('presets.module.index');
    }

    public function edit($id)
    {
        $preset = AttributePreset::with('attributes.attribute')->findOrFail($id);
        $this->setView('products::presets.form');
        $this->addViewData([
            'preset'        => $preset,
            'allAttributes' => $this->getAllAttributesFlat(),
            'title'         => 'Редактирование: ' . $preset->name,
        ]);
        return view($this->getView(), $this->getViewData());
    }

    public function update(Request $request, $id)
    {
        $preset = AttributePreset::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:attribute_presets,name,' . $id,
            'description' => 'nullable|string',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['name', 'description']);
        $data['attributes'] = $this->cleanAttributes($request->input('attributes', []));
        $this->presetService->update($preset, $data);
        return redirect()->route('presets.module.index');
    }

    public function destroy($id)
    {
        $preset = AttributePreset::findOrFail($id);
        $this->presetService->delete($preset);
        return redirect()->route('presets.module.index');
    }

    private function cleanAttributes(array $attributes): array
    {
        $cleaned = [];
        foreach ($attributes as $attr) {
            if (empty($attr['attribute_id'])) continue;
            $cleaned[] = [
                'attribute_id' => $attr['attribute_id'],
                'sort'         => $attr['sort'] ?? 0,
            ];
        }
        return $cleaned;
    }
}
