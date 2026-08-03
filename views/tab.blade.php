<div id="variants-app" data-product-id="{{ $productId }}">
    <p id="no-attributes-msg" style="display:none;">
        <i class="fa fa-info-circle"></i> Чтобы добавить вариации, сначала <a href="#"
            id="setup-fields-link">настройте поля</a>.
    </p>
    <div id="variants-content" style="display:none;">
        <p>
            <button class="btn btn-sm btn-secondary js-setup-fields-btn">
                <i class="fa fa-cog"></i> Настроить поля
            </button>
            <button class="btn btn-sm btn-success js-save-as-preset-btn">
                <i class="fa fa-floppy-o"></i> Сохранить как пресет
            </button>
        </p>
        <table class="table" id="variants-table">
            <thead id="variants-header"></thead>
            <tbody></tbody>
        </table>
        <button id="add-variant-btn" class="btn btn-success" style="display:none;">Добавить вариацию</button>
    </div>
</div>

<x-products::modals.setup-fields id="setupFieldsModal" />
<x-products::modals.attribute-form id="addAttributeModal" title="Новый атрибут" />
<x-products::modals.attribute-form id="editAttributeModal" title="Редактировать атрибут" />
<x-products::modals.categories id="manageCategoriesModal" />
<x-products::modals.save-preset id="savePresetModal" />

<div id="variantModal" class="product-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10500;">
    <div
        style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:4px; padding:20px; max-width:600px; width:90%; display:flex; flex-direction:column; max-height:80vh;">
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-shrink:0;">
            <h5 id="variantModalLabel" style="margin:0;">Вариация</h5>
            <span class="js-close-modal" style="cursor:pointer; font-size:20px; line-height:1;">&times;</span>
        </div>
        <div id="variantModalBody" style="overflow-y:auto; flex:1; min-height:0;">
        </div>
    </div>
</div>
<script>
    var variantModuleId = '{{ $moduleId }}';
</script>
<script src="{{ asset('/js/product-variants.js') }}"></script>
<script>
    jQuery(document).ready(function() {
        VariantsTab.init({
            productId: {{ $productId }},
            csrfToken: '{{ csrf_token() }}',
            urls: {
                categoriesList: '/admin/product-variants/categories',
                categoriesStore: '/admin/product-variants/categories',
                categoriesUpdate: '/admin/product-variants/categories',
                categoriesDelete: '/admin/product-variants/categories',
                attributesList: '/admin/product-variants/attributes',
                attributesStore: '/admin/product-variants/attributes',
                attributesAssign: '/admin/product-variants/attributes/assign',
                variantsList: '/admin/product-variants',
                variantsCreate: '/admin/product-variants/create',
                savePreset: '/admin/product-variants/save-as-preset',
                attributesTypes: '/admin/product-variants/attributes/types'
            }
        });
    });
</script>
