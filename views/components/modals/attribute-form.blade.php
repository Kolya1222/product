@props(['id' => 'attributeModal', 'title' => 'Новый атрибут'])

<div id="{{ $id }}" class="product-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10505;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:4px; padding:20px; max-width:400px; width:90%;">
        <h5>{{ $title }}</h5>
        <div class="form-group">
            <label>Название</label>
            <input type="text" class="form-control js-attr-name" placeholder="Например: Цена">
        </div>
        <div class="form-group">
            <label>Код (системное имя)</label>
            <input type="text" class="form-control js-attr-code" placeholder="Например: price">
        </div>
        <div class="form-group">
            <label>Тип поля</label>
            <select class="form-control js-attr-type">
                <option value="text">Текст</option>
                <option value="number">Число</option>
                <option value="select">Список</option>
            </select>
        </div>
        <div class="form-group">
            <label>Категория</label>
            <select class="form-control js-attr-category">
                <option value="">Без категории</option>
            </select>
        </div>
        <div class="form-group js-attr-options-group" style="display:none;">
            <label>Варианты (через запятую)</label>
            <input type="text" class="form-control js-attr-options" placeholder="Красный,Синий,Черный">
        </div>
        <button type="button" class="btn btn-primary js-save-attr">Сохранить</button>
        <button type="button" class="btn btn-secondary js-close-modal">Отмена</button>
    </div>
</div>