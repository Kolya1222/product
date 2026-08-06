<form id="general-attrs-form" onsubmit="return false;">
    <input type="hidden" name="product_id" value="{{ $productId }}">
    
    @foreach($fields as $field)
        <div class="form-group">
            <label>{!! $field['label'] !!}</label>
            {!! $field['html'] !!}
        </div>
    @endforeach
    
    @if(empty($fields))
        <p class="text-center text-muted">Нет назначенных характеристик. Сначала настройте поля.</p>
    @endif
</form>