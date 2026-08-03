@props(['id' => 'categoriesModal'])

<div id="{{ $id }}" class="product-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10506;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:4px; padding:20px; max-width:400px; width:90%;">
        <h5>Управление категориями</h5>
        <div class="js-categories-list" style="max-height:250px; overflow-y:auto; margin-bottom:10px;"></div>
        <div class="form-group">
            <input type="text" class="form-control js-new-category-name" placeholder="Название новой категории">
        </div>
        <button type="button" class="btn btn-sm btn-primary js-add-category">Добавить</button>
        <hr>
        <button type="button" class="btn btn-secondary js-close-modal">Закрыть</button>
    </div>
</div>