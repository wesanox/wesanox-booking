jQuery(document).ready( function($) {

    modalShow('opening_modal');
    modalClose('opening_modal');

    modalShow('area_modal');
    modalClose('area_modal');

    modalShow('room_modal');
    modalClose('room_modal');

    ajaxInsertDataForm('#opening' , 'opening')
    ajaxInsertDataForm('#area' , 'area')
    ajaxInsertDataForm('#room' , 'room')

    ajaxDeleteData()

    $('.modal').each(function () {
        if ( $(this).attr("data-id") ) {
            ajaxInsertDataForm('#' + $(this).attr("data-id"), 'opening')
        }
    })

    // $('.button-edit').each(function () {
    //     $(this).on('click', function() {
    //         let modal_id = $(this).parent().data('id');
    //
    //         modalShowEdit('booking_modal_' + modal_id);
    //         modalClose('booking_modal_' + modal_id);
    //
    //         modalShowEdit('account_modal_' + modal_id);
    //         modalClose('account_modal_' + modal_id);
    //
    //         modalShowEdit('room_modal_' + modal_id);
    //         modalClose('room_modal_' + modal_id);
    //     })
    // })
})

function modalShow (modalName)
{
    jQuery('#show_' + modalName).on('click', function() {
        jQuery('#add_' + modalName).show();
    });
}

function modalShowEdit (modalName)
{
    jQuery('#add_' + modalName).show();
}

function modalClose (modalName)
{
    jQuery('.modal-close').on('click', function() {
        jQuery('#add_' + modalName).hide();
    });
}