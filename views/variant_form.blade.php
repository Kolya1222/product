<form id="variant-form" onsubmit="return false;">
    @if(isset($variant))
        <input type="hidden" name="id" value="{{ $variant->id }}">
    @endif
    <input type="hidden" name="product_id" value="{{ $productId }}">
    <input type="hidden" name="_token" id="csrf-token" value="{{ csrf_token() }}">

    @foreach($fields as $field)
        <div class="form-group">
            <label>{!! $field['label'] !!}</label>
            {!! $field['html'] !!}
        </div>
    @endforeach

    <button type="button" id="save-variant-btn" class="btn btn-primary">Сохранить</button>
    <button id="close-popup-btn" class="btn">Отмена</button>
</form>