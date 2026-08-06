(function ($) {
    var Core = {
        csrfToken: null,
        urls: {},
        optionTypes: ['select', 'dropdown', 'listbox', 'listbox-multiple', 'option', 'checkbox'],

        loadTvTypes: function () {
            if (!this.urls.attributesTypes) return;
            var self = this;
            $.getJSON(this.urls.attributesTypes, function (response) {
                var types = response.types;
                $('.js-attr-type').each(function () {
                    var $select = $(this);
                    var currentVal = $select.val();
                    $select.empty();
                    $select.append('<option value="">-- Выберите тип --</option>');
                    types.forEach(function (group) {
                        var optgroup = $('<optgroup>').attr('label', group.optgroup.name);
                        $.each(group.optgroup.options, function (value, label) {
                            optgroup.append($('<option>').val(value).text(label));
                        });
                        $select.append(optgroup);
                    });
                    if (currentVal) $select.val(currentVal);
                    $select.trigger('change');
                });
            });
        },

        loadCategories: function (selector, selectedId) {
            var self = this;
            $.get(this.urls.categoriesList, function (response) {
                var cats = Array.isArray(response) ? response : (response.data || response.categories || []);
                var options = '<option value="">Без категории</option>';
                cats.forEach(function (c) {
                    options += '<option value="' + c.id + '"' + (c.id == selectedId ? ' selected' : '') + '>' + c.name + '</option>';
                });
                $(selector).html(options);
            });
        },

        loadCategoriesList: function () {
            var self = this;
            $.get(this.urls.categoriesList, function (response) {
                var cats = Array.isArray(response) ? response : (response.data || response.categories || []);
                var html = '';
                cats.forEach(function (c) {
                    html += '<div style="margin-bottom:3px;">' +
                        '<span>' + c.name + '</span> ' +
                        '<button class="btn btn-xs btn-info js-edit-category" data-id="' + c.id + '"><i class="fa fa-pencil"></i></button> ' +
                        '<button class="btn btn-xs btn-danger js-delete-category" data-id="' + c.id + '"><i class="fa fa-trash"></i></button>' +
                        '</div>';
                });
                $('#manageCategoriesModal .js-categories-list').html(html || '<p>Нет категорий</p>');
            });
        },

        bindCategoryEvents: function () {
            var self = this;

            $('#manageCategoriesModal .js-add-category').off('click').on('click', function () {
                var name = $('#manageCategoriesModal .js-new-category-name').val().trim();
                if (!name) return alert('Введите название');
                $.ajax({
                    url: self.urls.categoriesStore,
                    method: 'POST',
                    data: { name: name, _token: self.csrfToken },
                    success: function () {
                        $('#manageCategoriesModal .js-new-category-name').val('');
                        self.loadCategoriesList();
                    },
                    error: function (xhr) {
                        alert('Ошибка: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            $(document).off('click', '.js-edit-category').on('click', '.js-edit-category', function () {
                var id = $(this).data('id');
                var newName = prompt('Новое название:');
                if (newName) {
                    $.ajax({
                        url: self.urls.categoriesUpdate + '/' + id,
                        method: 'PUT',
                        data: { name: newName, _token: self.csrfToken },
                        success: function () { self.loadCategoriesList(); }
                    });
                }
            });

            $(document).off('click', '.js-delete-category').on('click', '.js-delete-category', function () {
                if (!confirm('Удалить категорию? Атрибуты останутся, но потеряют привязку.')) return;
                var id = $(this).data('id');
                $.ajax({
                    url: self.urls.categoriesDelete + '/' + id,
                    method: 'DELETE',
                    data: { _token: self.csrfToken },
                    success: function () { self.loadCategoriesList(); }
                });
            });
        },

        bindAttributeCreation: function (modalSelector, successCallback) {
            var self = this;
            $(document).off('click', modalSelector + ' .js-save-attr').on('click', modalSelector + ' .js-save-attr', function () {
                var modal = $(modalSelector);
                var name = modal.find('.js-attr-name').val();
                var code = modal.find('.js-attr-code').val();
                var type = modal.find('.js-attr-type').val();
                var options = null;
                if (Core.optionTypes.includes(type)) {
                    var optStr = modal.find('.js-attr-options').val();
                    if (optStr) options = optStr.split(',').map(s => s.trim());
                }
                $.ajax({
                    url: self.urls.attributesStore,
                    method: 'POST',
                    data: {
                        name: name,
                        code: code,
                        field_type: type,
                        options: options,
                        category_id: modal.find('.js-attr-category').val() || null,
                        _token: self.csrfToken
                    },
                    success: function (response) {
                        modal.hide();
                        modal.find('.js-attr-name, .js-attr-code, .js-attr-options').val('');
                        if (successCallback) successCallback(response);
                    },
                    error: function (xhr) {
                        alert('Ошибка: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });
        },

        bindCloseModal: function () {
            $(document).off('click', '.js-close-modal').on('click', '.js-close-modal', function () {
                $(this).closest('.product-modal').hide();
            });
        },

        bindAttrTypeToggle: function () {
            $(document).off('change', '.js-attr-type').on('change', '.js-attr-type', function () {
                var $modal = $(this).closest('.product-modal');
                var type = $(this).val();
                var showOptions = Core.optionTypes.includes(type);
                $modal.find('.js-attr-options-group').toggle(showOptions);
            });
        }
    };

    window.PresetsForm = {
        init: function (config) {
            Core.csrfToken = config.csrfToken;
            Core.urls = config.urls;
            Core.loadTvTypes();
            this.allAttributes = config.allAttributes || [];
            this.attrIndex = config.attrCount || 0;

            Core.bindCloseModal();
            Core.bindAttrTypeToggle();
            Core.bindCategoryEvents();

            var self = this;
            Core.bindAttributeCreation('#addAttributeModal', function (response) {
                if (response.attribute) {
                    self.allAttributes.push(response.attribute);
                    self.refreshAllAttrSelects();
                    var row = '<div class="attr-row" style="margin-bottom:5px;">' +
                        '<select name="attributes[' + self.attrIndex + '][attribute_id]" class="form-control attr-select" data-selected="' + response.attribute.id + '" style="width:250px; display:inline-block;">' +
                        '<option value="">-- Выберите атрибут --</option>' +
                        self.allAttributes.map(function (a) {
                            return '<option value="' + a.id + '"' + (a.id == response.attribute.id ? ' selected' : '') + '>' + a.name + ' (' + a.code + ')</option>';
                        }).join('') +
                        '</select>' +
                        '<button type="button" class="btn btn-xs btn-danger remove-attr" style="margin-left:5px;"><i class="fa fa-trash"></i></button>' +
                        '</div>';
                    $('#attributes-container').append(row);
                    self.attrIndex++;
                }
            });

            $('#create-attribute-btn').off('click').on('click', function () {
                Core.loadCategories('#addAttributeModal .js-attr-category', '');
                $('#addAttributeModal').show();
            });

            $('#manage-categories-btn').off('click').on('click', function () {
                Core.loadCategoriesList();
                $('#manageCategoriesModal').show();
            });

            $('#add-attr').off('click').on('click', function () {
                var row = '<div class="attr-row" style="margin-bottom:5px;">' +
                    '<select name="attributes[' + self.attrIndex + '][attribute_id]" class="form-control attr-select" data-selected="" style="width:250px; display:inline-block;">' +
                    '<option value="">-- Выберите атрибут --</option>' +
                    self.allAttributes.map(function (a) {
                        return '<option value="' + a.id + '">' + a.name + ' (' + a.code + ')</option>';
                    }).join('') +
                    '</select>' +
                    '<button type="button" class="btn btn-xs btn-danger remove-attr" style="margin-left:5px;"><i class="fa fa-trash"></i></button>' +
                    '</div>';
                $('#attributes-container').append(row);
                self.attrIndex++;
            });

            $(document).off('click', '.remove-attr').on('click', '.remove-attr', function () {
                $(this).closest('.attr-row').remove();
            });

            this.refreshAllAttrSelects();
        },

        refreshAllAttrSelects: function () {
            var self = this;
            $('.attr-select').each(function () {
                var $this = $(this);
                var selected = $this.data('selected') || $this.val();

                var options = '<option value="">-- Выберите атрибут --</option>';
                self.allAttributes.forEach(function (a) {
                    options += '<option value="' + a.id + '"' + (a.id == selected ? ' selected' : '') + '>' + a.name + ' (' + a.code + ')</option>';
                });
                $this.html(options);
            });
        },
    };

    window.VariantsTab = {
        init: function (config) {
            Core.csrfToken = config.csrfToken;
            Core.urls = config.urls;
            Core.loadTvTypes();
            this.productId = config.productId;
            this.currentAttributes = [];

            Core.bindCloseModal();
            Core.bindAttrTypeToggle();
            Core.bindCategoryEvents();

            var self = this;

            $('#setup-fields-link').off('click').on('click', function (e) {
                e.preventDefault();
                self.refreshCheckboxes();
                $('#setupFieldsModal').show();
            });

            $('.js-setup-fields-btn').off('click').on('click', function () {
                self.refreshCheckboxes();
                $('#setupFieldsModal').show();
            });

            $('#setupFieldsModal .js-save-fields-btn').off('click').on('click', function () {
                var ids = $('#setupFieldsModal .js-attributes-checkboxes input:checked').map(function () {
                    return this.value;
                }).get();
                $.post(Core.urls.attributesAssign, {
                    product_id: self.productId,
                    attribute_ids: ids,
                    _token: Core.csrfToken
                }, function () {
                    $('#setupFieldsModal').hide();
                    self.checkAssignedAttributes();
                });
            });

            $('#setupFieldsModal .js-add-attribute-btn').off('click').on('click', function () {
                Core.loadCategories('#addAttributeModal .js-attr-category', '');
                $('#addAttributeModal').show();
            });

            Core.bindAttributeCreation('#addAttributeModal', function () {
                self.refreshCheckboxes();
            });

            $(document).off('click', '.js-edit-attr-btn').on('click', '.js-edit-attr-btn', function (e) {
                e.stopPropagation();
                var attrId = $(this).data('id');
                $.get(Core.urls.attributesList + '/' + attrId, function (response) {
                    if (response.success) {
                        var attr = response.attribute;
                        var modal = $('#editAttributeModal');
                        modal.find('.js-attr-name').val(attr.name);
                        modal.find('.js-attr-code').val(attr.code);
                        modal.find('.js-attr-type').val(attr.field_type);
                        if (Core.optionTypes.includes(attr.field_type)) {
                            modal.find('.js-attr-options-group').show();
                            modal.find('.js-attr-options').val(attr.options ? attr.options.join(',') : '');
                        } else {
                            modal.find('.js-attr-options-group').hide();
                        }
                        Core.loadCategories('#editAttributeModal .js-attr-category', attr.category_id);
                        modal.data('attrId', attr.id).show();
                    }
                });
            });

            $('#editAttributeModal .js-save-attr').off('click').on('click', function () {
                var modal = $('#editAttributeModal');
                var id = modal.data('attrId');
                var name = modal.find('.js-attr-name').val();
                var code = modal.find('.js-attr-code').val();
                var type = modal.find('.js-attr-type').val();
                var options = null;
                if (Core.optionTypes.includes(type)) {
                    var optStr = modal.find('.js-attr-options').val();
                    if (optStr) options = optStr.split(',').map(s => s.trim());
                }
                $.ajax({
                    url: Core.urls.attributesList + '/' + id,
                    method: 'PUT',
                    data: {
                        name: name,
                        code: code,
                        field_type: type,
                        options: options,
                        category_id: modal.find('.js-attr-category').val() || null,
                        _token: Core.csrfToken
                    },
                    success: function () {
                        modal.hide();
                        self.refreshCheckboxes();
                    },
                    error: function (xhr) {
                        alert('Ошибка: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            $(document).off('click', '.js-delete-attr-btn').on('click', '.js-delete-attr-btn', function (e) {
                e.stopPropagation();
                if (!confirm('Удалить атрибут? Это действие нельзя отменить.')) return;
                var attrId = $(this).data('id');
                $.ajax({
                    url: Core.urls.attributesList + '/' + attrId,
                    method: 'DELETE',
                    data: { _token: Core.csrfToken },
                    success: function () { self.refreshCheckboxes(); },
                    error: function (xhr) {
                        alert('Ошибка: ' + (xhr.responseJSON?.message || 'Не удалось удалить атрибут'));
                    }
                });
            });

            $(document).off('click', '.js-manage-categories-btn').on('click', '.js-manage-categories-btn', function () {
                Core.loadCategoriesList();
                $('#manageCategoriesModal').show();
            });

            $('#add-variant-btn').off('click').on('click', function () {
                self.openVariantForm('/manager/modules/' + variantModuleId + '/variant/create?product_id=' + self.productId);
            });

            $(document).off('click', '.edit-variant').on('click', '.edit-variant', function () {
                var id = $(this).data('id');
                self.openVariantForm('/manager/modules/' + variantModuleId + '/variant/' + id + '/edit');
            });

            $(document).off('dblclick', '#variants-table tbody tr').on('dblclick', '#variants-table tbody tr', function () {
                var id = $(this).data('id');
                if (id) self.openVariantForm('/manager/modules/' + variantModuleId + '/variant/' + id + '/edit');
            });

            $(document).off('click', '#save-variant-btn').on('click', '#save-variant-btn', function () {
                var form = $('#variant-form');
                var id = form.find('input[name="id"]').val();
                var method = id ? 'PUT' : 'POST';
                var url = id ? '/admin/product-variants/' + id : '/admin/product-variants';
                var data = {
                    product_id: form.find('input[name="product_id"]').val(),
                    _token: form.find('input[name="_token"]').val(),
                    attrs: {}
                };
                $('[name^="attrs["]').each(function () {
                    var name = $(this).attr('name');
                    var code = name.match(/\[(.*?)\]/)[1];
                    data.attrs[code] = $(this).val();
                });
                $.ajax({
                    url: url,
                    method: method,
                    data: data,
                    success: function () {
                        $('#variantModal').hide();
                        self.loadVariants();
                    },
                    error: function (xhr) {
                        alert('Ошибка сохранения: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            $(document).off('click', '#close-popup-btn').on('click', '#close-popup-btn', function () {
                $('#variantModal').hide();
            });

            $(document).off('click', '.delete-variant').on('click', '.delete-variant', function () {
                if (!confirm('Удалить?')) return;
                var id = $(this).data('id');
                $.ajax({
                    url: '/admin/product-variants/' + id,
                    method: 'DELETE',
                    data: { _token: Core.csrfToken },
                    success: function () { self.loadVariants(); },
                    error: function (xhr) {
                        alert('Ошибка удаления: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            $('.js-save-as-preset-btn').off('click').on('click', function () {
                if (!self.currentAttributes.length) {
                    alert('Нет назначенных атрибутов.');
                    return;
                }
                $('#savePresetModal .js-preset-name').val('');
                $('#savePresetModal .js-preset-description').val('');
                $('#savePresetModal').show();
            });

            $('#savePresetModal .js-save-preset-confirm').off('click').on('click', function () {
                var name = $('#savePresetModal .js-preset-name').val().trim();
                if (!name) return alert('Введите название');
                var desc = $('#savePresetModal .js-preset-description').val().trim();
                var attrIds = self.currentAttributes.map(function (a) { return a.id; });
                $.ajax({
                    url: Core.urls.savePreset,
                    method: 'POST',
                    data: {
                        name: name,
                        description: desc,
                        attribute_ids: attrIds,
                        _token: Core.csrfToken
                    },
                    success: function () {
                        $('#savePresetModal').hide();
                        alert('Пресет "' + name + '" создан.');
                    },
                    error: function (xhr) {
                        alert('Ошибка: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            this.checkAssignedAttributes();
        },

        refreshCheckboxes: function () {
            var self = this;
            $.get(Core.urls.attributesList, { product_id: this.productId }, function (data) {
                var html = '';
                data.categories.forEach(function (group) {
                    html += '<h6>' + group.category.name + '</h6>';
                    group.attributes.forEach(function (a) {
                        html += '<div><label><input type="checkbox" value="' + a.id + '" ' + (a.assigned ? 'checked' : '') + '> ' + a.name + ' (' + a.code + ')</label> ';
                        html += '<button class="btn btn-xs btn-secondary js-edit-attr-btn" data-id="' + a.id + '"><i class="fa fa-pencil"></i></button>';
                        html += '<button class="btn btn-xs btn-danger js-delete-attr-btn" data-id="' + a.id + '"><i class="fa fa-trash"></i></button></div>';
                    });
                });
                $('#setupFieldsModal .js-attributes-checkboxes').html(html);
                var all = [];
                data.categories.forEach(function (g) { all = all.concat(g.attributes); });
                self.currentAttributes = all.filter(function (a) { return a.assigned; });
                if (self.currentAttributes.length) {
                    $('#no-attributes-msg').hide();
                    $('#variants-content').show();
                    self.loadVariants();
                } else {
                    self.currentAttributes = [];
                    $('#no-attributes-msg').show();
                    $('#variants-content').hide();
                }
            });
        },

        checkAssignedAttributes: function () {
            this.refreshCheckboxes();
        },

        loadVariants: function () {
            var self = this;
            $.get(Core.urls.variantsList, { product_id: this.productId }, function (variants) {
                if (!self.currentAttributes.length) {
                    $('#variants-header, #variants-table tbody').html('');
                    $('#add-variant-btn').hide();
                    return;
                }
                var header = '<tr>' + self.currentAttributes.map(function (a) { return '<th>' + a.name + '</th>'; }).join('') + '<th></th></tr>';
                $('#variants-header').html(header);
                var rows = '';
                variants.forEach(function (v) {
                    var cells = self.currentAttributes.map(function (a) { return '<td>' + (v.attrs[a.code] || '') + '</td>'; }).join('');
                    rows += '<tr data-id="' + v.id + '">' + cells +
                        '<td><button class="btn btn-sm btn-info edit-variant" data-id="' + v.id + '">Ред.</button> ' +
                        '<button class="btn btn-sm btn-danger delete-variant" data-id="' + v.id + '">Удал.</button></td></tr>';
                });
                $('#variants-table tbody').html(rows);
                $('#add-variant-btn').show();
            });
        },

        openVariantForm: function (url) {
            $('#variantModalBody').html('<p class="text-center">Загрузка...</p>');
            $('#variantModal').show();
            var self = this;
            $.get(url, function (html) {
                $('#variantModalBody').html(html);
                $('#variant-form input[name="_token"]').val(Core.csrfToken);
            });
        }
    };
    window.GeneralAttributesTab = {
        init: function (config) {
            this.productId = config.productId;
            this.urls = config.urls;
            this.csrfToken = config.csrfToken;
            var self = this;

            $('.js-setup-general-fields-btn').off('click').on('click', function () {
                self.refreshCheckboxes();
                $('#setupGeneralFieldsModal').show();
            });

            $('#setupGeneralFieldsModal .js-save-fields-btn').off('click').on('click', function () {
                var ids = $('#setupGeneralFieldsModal .js-attributes-checkboxes input:checked').map(function () {
                    return this.value;
                }).get();

                $.ajax({
                    url: self.urls.attributesAssign,
                    method: 'POST',
                    data: {
                        product_id: self.productId,
                        attribute_ids: ids,
                        _token: self.csrfToken
                    },
                    success: function () {
                        $('#setupGeneralFieldsModal').hide();
                        self.checkAssigned();
                    },
                    error: function (xhr) {
                        alert('Ошибка: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            $('.js-edit-general-fields-btn').off('click').on('click', function () {
                self.openGeneralForm();
            });

            $('#save-general-attrs-btn').off('click').on('click', function () {
                var form = $('#general-attrs-form');
                var data = {
                    product_id: form.find('input[name="product_id"]').val(),
                    _token: self.csrfToken,
                    attrs: {}
                };

                $('[name^="attrs["]').each(function () {
                    var name = $(this).attr('name');
                    var code = name.match(/\[(.*?)\]/)[1];
                    if ($(this).attr('type') === 'checkbox') {
                        data.attrs[code] = $(this).is(':checked') ? $(this).val() : '';
                    } else {
                        data.attrs[code] = $(this).val();
                    }
                });

                $.ajax({
                    url: self.urls.generalSave,
                    method: 'POST',
                    data: data,
                    success: function () {
                        $('#generalAttrsModal').hide();
                    },
                    error: function (xhr) {
                        alert('Ошибка сохранения: ' + (xhr.responseJSON?.message || ''));
                    }
                });
            });

            this.checkAssigned();
        },

        refreshCheckboxes: function () {
            var self = this;
            $.get(this.urls.attributesList, { product_id: this.productId, type: 'general' }, function (data) {
                var html = '';
                data.categories.forEach(function (group) {
                    html += '<h6>' + group.category.name + '</h6>';
                    group.attributes.forEach(function (a) {
                        html += '<div><label><input type="checkbox" value="' + a.id + '" ' + (a.assigned ? 'checked' : '') + '> ' + a.name + ' (' + a.code + ')</label></div>';
                    });
                });
                $('#setupGeneralFieldsModal .js-attributes-checkboxes').html(html);
            });
        },

        checkAssigned: function () {
            var self = this;
            $.get(this.urls.attributesList, { product_id: this.productId, type: 'general' }, function (data) {
                var hasAssigned = false;
                data.categories.forEach(function (g) {
                    if (g.attributes.some(a => a.assigned)) hasAssigned = true;
                });

                if (hasAssigned) {
                    $('.js-edit-general-fields-btn').show();
                } else {
                    $('.js-edit-general-fields-btn').hide();
                }
            });
        },

        openGeneralForm: function () {
            $('#generalAttrsModalBody').html('<p class="text-center">Загрузка...</p>');
            $('#generalAttrsModal').show();
            $.get(this.urls.generalForm, { product_id: this.productId }, function (html) {
                $('#generalAttrsModalBody').html(html);
            });
        }
    };
})(jQuery);