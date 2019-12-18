jQuery(function($) {
    'use strict';

    var selectors = {
        table: '#wc-ezdefi-order-assign',
        select: '#order-select',
        amountIdInput: '.amount-id-input',
        currencyInput: '.currency-input',
        orderIdInput: '.order-id-input',
        oldOrderIdInput: '.old-order-id-input',
        assignBtn: '.assignBtn',
        removeBtn: '.removeBtn',
        reverseBtn: '.reverseBtn',
        filterBtn: '.filterBtn',
        filterForm: '#wc-ezdefi-exception-table-filter-form',
        nav: '#wc-ezdefi-order-assign-nav',
        navBtn: '#wc-ezdefi-order-assign-nav a.button',
        showSelectBtn: '.showSelectBtn',
        hideSelectBtn: '.hideSelectBtn',
        savedOrder: '.saved-order',
        selectOrder: '.select-order',
    };

    var wc_ezdefi_assign = function() {
        this.$table = $(selectors.table);
        this.$nav = $(selectors.nav);
        this.$navBtn = this.$nav.find('a.button');
        // this.$select = this.$table.find(selectors.select);

        var init = this.init.bind(this);
        var onAssign = this.onAssign.bind(this);
        var onRemove = this.onRemove.bind(this);
        var onReverse = this.onReverse.bind(this);
        var onApplyFilter = this.onApplyFilter.bind(this);
        var onNavButtonClick = this.onNavButtonClick.bind(this);
        var onShowOrderSelect = this.onShowOrderSelect.bind(this);
        var onHideOrderSelect = this.onHideOrderSelect.bind(this);

        init();

        $(document.body)
            .on('click', selectors.assignBtn, onAssign)
            .on('click', selectors.removeBtn, onRemove)
            .on('click', selectors.reverseBtn, onReverse)
            .on('click', selectors.filterBtn, onApplyFilter)
            .on('click', selectors.navBtn, onNavButtonClick)
            .on('click', selectors.showSelectBtn, onShowOrderSelect)
            .on('click', selectors.hideSelectBtn, onHideOrderSelect);
    };

    wc_ezdefi_assign.prototype.init = function() {
        var data = {
            action: 'wc_ezdefi_get_exception',
        };
        this.getException.call(this, data);
    };

    wc_ezdefi_assign.prototype.onShowOrderSelect = function(e) {
        e.preventDefault();
        var column = $(e.target).closest('td');

        column.find(selectors.showSelectBtn).hide();
        column.find(selectors.hideSelectBtn).show();
        column.find(selectors.savedOrder).hide();
        column.find(selectors.selectOrder).show();

        this.initSelect2.call(this, column.find('select'));
    };

    wc_ezdefi_assign.prototype.initSelect2 = function(select) {
        var self = this;
        select.select2({
            width: '100%',
            ajax: {
                url: wc_ezdefi_data.ajax_url,
                type: 'POST',
                data: function(params) {
                    var query = {
                        action: 'wc_ezdefi_get_order',
                    };

                    return query;
                },
                processResults: function(data) {
                    return {
                        results: data.data
                    }
                },
                cache: true,
                dataType: 'json',
            },
            placeholder: 'Select Order',
            templateResult: self.formatOrderOption,
            templateSelection: self.formatOrderSelection,
            minimumResultsForSearch: Infinity
        });
        select.on('select2:select', this.onSelect2Select);
    };

    wc_ezdefi_assign.prototype.onSelect2Select = function(e) {
        var column = $(e.target).closest('td');
        var data = e.params.data;

        column.find(selectors.orderIdInput).val(data.id);
    };

    wc_ezdefi_assign.prototype.onHideOrderSelect = function(e) {
        e.preventDefault();
        var self = this;
        var column = $(e.target).closest('td');

        column.find(selectors.showSelectBtn).show();
        column.find(selectors.hideSelectBtn).hide();
        column.find(selectors.savedOrder).show();
        column.find(selectors.selectOrder).hide();

        column.find('select').select2('destroy');

        var savedOrderId = column.find('#saved-order-id').text();
        column.find(selectors.orderIdInput).val(savedOrderId);
    };

    wc_ezdefi_assign.prototype.onApplyFilter = function(e) {
        e.preventDefault();
        var data = this.getAjaxData();
        this.getException.call(this, data);
    };

    wc_ezdefi_assign.prototype.getAjaxData = function() {
        var form = $(selectors.filterForm);
        var data = {
            'action': 'wc_ezdefi_get_exception'
        };
        form.find('input, select').each(function() {
            var val = '';
            if($(this).is('input')) {
                val = $(this).val();
            }

            if($(this).is('select')) {
                val = $(this).find('option:selected').val();
            }

            if(val.length > 0) {
                data[$(this).attr('name')] = $(this).val();
            }
        });
        return data;
    };

    wc_ezdefi_assign.prototype.onNavButtonClick = function(e) {
        e.preventDefault();
        var button = $(e.target);

        if(button.is('.disabled')) {
            return false;
        }

        var current_page = parseInt(this.$nav.find('.tablenav-paging-text .number').text());
        var page;

        if(button.is('.prev-page')) {
            page = current_page - 1;
        } else {
            page = current_page + 1;
        }

        var data = this.getAjaxData();
        data['page'] = page;

        this.getException.call(this, data);
    };

    wc_ezdefi_assign.prototype.getException = function(data) {
        var self = this;
        $.ajax({
            url: wc_ezdefi_data.ajax_url,
            method: 'post',
            data: data,
            beforeSend: function() {
                self.$table.find('tbody tr').not('.spinner-row').remove();
                self.$table.find('tbody tr.spinner-row').show();
                self.$nav.hide();
            },
            success: function(response) {
                self.$table.find('tbody tr.spinner-row').hide();
                self.renderHtml.call(self, response.data.data);
                self.renderPagination.call(self, response.data.meta_data);
            }
        });
    };

    wc_ezdefi_assign.prototype.renderHtml = function(data) {
        var self = this;
        if(data.length === 0) {
            self.$table.append("<tr><td colspan='4'>Not found</td></tr>")
        }
        for(var i=0;i<data.length;i++) {
            var row = data[i];
            var status;
            var payment_method;
            switch (row['status']) {
                case 'not_paid':
                    status = 'Not paid';
                    break;
                case 'expired_done':
                    status = 'Paid after expired';
                    break;
                case 'done':
                    status = 'Paid on time';
                    break;
            }
            switch (row['payment_method']) {
                case 'amount_id':
                    payment_method = 'Pay with any crypto wallet';
                    break;
                case 'ezdefi_wallet':
                    payment_method = 'Pay with ezDeFi wallet';
                    break;
            }
            var html = $(
                "<tr>" +
                "<td class='amount-id-column'>" +
                    "<span>" + row['amount_id'] + "</span>" +
                    "<input type='hidden' class='amount-id-input' value='" + row['amount_id'] + "' >" +
                "</td>" +
                "<td>" +
                    "<span class='symbol'>" + row['currency'] + "</span>" +
                    "<input type='hidden' class='currency-input' value='" + row['currency'] + "' >" +
                "</td>" +
                "<td class='order-column'>" +
                    "<input type='hidden' class='old-order-id-input' value='" + ( (row['order_id']) ? row['order_id'] : '' ) + "' >" +
                    "<input type='hidden' class='order-id-input' value='" + ( (row['order_id']) ? row['order_id'] : '' ) + "' >" +
                    "<div class='saved-order'>" +
                        "<div>Order ID: <span id='saved-order-id'>" + row['order_id'] + "</span></div>" +
                        "<div>Email: " + row['billing_email'] + "</div>" +
                        "<div>Status: " + status + "</div>" +
                        "<div>Payment method: " + payment_method + "</div>" +
                    "</div>" +
                    "<div class='select-order' style='display: none'>" +
                        "<select name='' id=''></select>" +
                    "</div>" +
                    "<div class='actions'>" +
                        "<a href='' class='showSelectBtn button'>Assign to different order</a>" +
                        "<a href='' class='hideSelectBtn button' style='display: none'>Cancel</a>" +
                    "</div>" +
                "</td>" +
                "</tr>"
            );
            if(row['explorer_url'] && row['explorer_url'].length > 0) {
                var explore = $("<a class='explorer-url' href='" + row['explorer_url'] + "'>View Transaction Detail</a>");
                html.find('td.amount-id-column').append(explore);
            }

            if(row['order_id'] == null) {
                html.find('td.order-column .saved-order, td.order-column .actions').remove();
                html.find('td.order-column .select-order').show();
                self.initSelect2.call(self, html.find('td.order-column select'));
            }

            var last_td;

            if(row['status'] === 'done') {
                last_td = $(
                    "<td>" +
                    "<button class='button reverseBtn'>Reverse</button> " +
                    "</td>"
                );
            } else {
                last_td = $(
                    "<td>" +
                    "<button class='button button-primary assignBtn'>Confirm Paid</button> " +
                    "<button class='button removeBtn'>Remove</button>" +
                    "</td>"
                );
            }
            html.append(last_td);
            html.appendTo(self.$table.find('tbody'));
        }
    };

    wc_ezdefi_assign.prototype.renderPagination = function(data) {
        this.$nav.show();
        this.$nav.find('.displaying-num .number').text(data['total']);
        this.$nav.find('.tablenav-paging-text .number').text(data['current_page']);
        this.$nav.find('.tablenav-paging-text .total-pages').text(data['total_pages']);

        if( data['current_page'] === 1 ) {
            this.$nav.find('.prev-page').addClass('disabled')
        } else {
            this.$nav.find('.prev-page').removeClass('disabled')
        }

        if( data['current_page'] === data['total_pages'] ) {
            this.$nav.find('.next-page').addClass('disabled')
        } else {
            this.$nav.find('.next-page').removeClass('disabled')
        }

        this.$nav.show();
    };

    wc_ezdefi_assign.prototype.formatOrderOption = function(order) {
        if (order.loading) {
            return 'Loading';
        }

        var $container = $(
            "<div class='select2-order'>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Order ID:</strong></div>" +
            "<div class='right'>" + order['id'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Total:</strong></div>" +
            "<div class='right'>" + order['currency'] + " " + order['total'] + " ~ " + order['amount_id'] + " " + order['token'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Billing Email:</strong></div>" +
            "<div class='right'>" + order['billing_email'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Date created:</strong></div>" +
            "<div class='right'>" + order['date_created'] + "</div>" +
            "</div>" +
            "</div>"
        );
        return $container;
    };

    wc_ezdefi_assign.prototype.formatOrderSelection = function(order) {
        return 'Order ID: ' + order['id'];
    };

    wc_ezdefi_assign.prototype.onAssign = function(e) {
        e.preventDefault();
        var self = this;
        var row = $(e.target).closest('tr');
        var old_order_id = row.find(selectors.oldOrderIdInput).val();
        var order_id = row.find(selectors.orderIdInput).val();
        var amount_id = row.find(selectors.amountIdInput).val();
        var currency = row.find(selectors.currencyInput).val();
        var data = {
            action: 'wc_ezdefi_assign_amount_id',
            old_order_id: old_order_id,
            order_id: order_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data).success(function() {
            var data = self.getAjaxData();
            self.getException(data);
        });
    };

    wc_ezdefi_assign.prototype.onReverse = function(e) {
        e.preventDefault();
        var self = this;
        var row = $(e.target).closest('tr');
        var order_id = row.find(selectors.orderIdInput).val();
        var amount_id = row.find(selectors.amountIdInput).val();
        var currency = row.find(selectors.currencyInput).val();
        var data = {
            action: 'wc_ezdefi_reverse_order',
            order_id: order_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data).success(function() {
            var data = self.getAjaxData();
            self.getException(data);
        });
    };

    wc_ezdefi_assign.prototype.onRemove = function(e) {
        e.preventDefault();
        if(!confirm('Do you want to delete this amount ID')) {
            return false;
        }
        var self = this;
        var row = $(e.target).closest('tr');
        var order_id = row.find(selectors.orderIdInput).val();
        var amount_id = row.find(selectors.amountIdInput).val();
        var currency = row.find(selectors.currencyInput).val();
        var data = {
            action: 'wc_ezdefi_delete_amount_id',
            order_id: order_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data).success(function() {
            var data = self.getAjaxData();
            self.getException(data);
        });
    };

    wc_ezdefi_assign.prototype.callAjax = function(data) {
        var self = this;
        return $.ajax({
            url: wc_ezdefi_data.ajax_url,
            method: 'post',
            data: data,
            beforeSend: function() {
                self.$table.find('tbody tr').not('.spinner-row').remove();
                self.$table.find('tbody tr.spinner-row').show();
                self.$nav.hide();
            }
        });
    };

    new wc_ezdefi_assign();
});