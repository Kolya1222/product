@props(['id' => 'savePresetModal'])

<div id="{{ $id }}" class="product-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10504;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:4px; padding:20px; max-width:400px; width:90%;">
        <h5>Сохранить как пресет</h5>
        <div class="form-group">
            <label>Название пресета</label>
            <input type="text" class="form-control js-preset-name" placeholder="Например: MacBook Pro 16">
        </div>
        <div class="form-group">
            <label>Описание</label>
            <textarea class="form-control js-preset-description" rows="2"></textarea>
        </div>
        <button type="button" class="btn btn-primary js-save-preset-confirm">Сохранить</button>
        <button type="button" class="btn btn-secondary js-close-modal">Отмена</button>
    </div>
</div>