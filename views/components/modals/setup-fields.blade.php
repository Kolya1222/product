@props(['id' => 'setupFieldsModal'])

<div id="{{ $id }}" class="product-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10501;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:4px; padding:20px; max-width:500px; width:90%;">
        <h5>Выберите атрибуты для вариаций</h5>
        <div class="js-attributes-checkboxes" style="max-height:300px; overflow-y:auto;"></div>
        <button type="button" class="btn btn-sm btn-secondary js-add-attribute-btn">+ Создать атрибут</button>
        <hr>
        <button type="button" class="btn btn-primary js-save-fields-btn">Сохранить</button>
        <button type="button" class="btn btn-secondary js-close-modal">Отмена</button>
    </div>
</div>