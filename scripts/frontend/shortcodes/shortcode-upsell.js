jQuery(document).ready(function($){
    /**
     * Zusatzversichung change in the cart
     */
    $('body').on('click', '.checkbox-extras', function() {
        var product_id = $(this).data("product_id");
        var quant = $(this).data('quant');
        var url = '';

        if($(this).hasClass('active')) {
            url = '/wp-admin/admin-ajax.php?wc-ajax=remove_product_from_cart';
            $(this).removeClass('active');
        } else {
            url = '/wp-admin/admin-ajax.php?wc-ajax=wesanox_upsell_to_card';
            $(this).addClass('active');
        }

        if( $(this).hasClass('btn-active') ) {
            $(this).removeClass('btn-active').html('JETZT HINZUFÜGEN');
        }

        if ( $(this).hasClass('btn-primary') ) {
            $(this).addClass('btn-active').html('AUSGEWÄHLT');
        }

        jQuery.ajax({
            type: 'POST',
            url: url,
            data: {
                action: $(this).hasClass('active') ? 'wesanox_upsell_to_card' : 'remove_product_from_cart',
                product_id: product_id,
                quant: quant
            },
            success: function(response) {
                if (response === 'success') {
                    jQuery.ajax({
                        type: 'POST',
                        url: '/wp-admin/admin-ajax.php?wc-ajax=refresh_cart',
                        data: {
                            action: 'refresh_cart',
                        },
                        success: function (html) {
                            jQuery('#cart-segment').html(html);

                            // Trigger the wc_fragment_refresh event after the cart HTML is updated
                            $('body').trigger('wc_fragment_refresh');
                        }
                    });
                }
            }
        });
    });
})