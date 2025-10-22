jQuery(function($){
    const ROOT = '#element-booking-persons';

    const s = bookingStore.get();

    if (s.person_count && s.person_count >= 1 && s.person_count <= 4) {
        setPersonUI(s.person_count);
    } else {
        const fallback = Number($(`${ROOT} input.person:checked`).attr('name')) || 1;
        bookingStore.set({ person_count: fallback });
        setPersonUI(fallback);
    }

    bookingStore.onChange(next => setPersonUI(next.person_count));

    $('body').on('click', `${ROOT} a.active`, function(e) {
        const count = Number($(`${ROOT} input.person:checked`).attr('name')) || 1;

        bookingStore.set({ person_count: count });

        $('#count-people .checked').removeClass('d-none').addClass('d-flex');
        $('#count-people .not-checked').removeClass('d-flex').addClass('d-none');

        let btn_id          = $('#' + $(this).attr('value').replace('-box', ''));

        btn_id.addClass('active');
        btn_id.addClass('swiper-slide-active');
        btn_id.attr('href', '#' + btn_id.attr('id')  + '-slide');

        window.swiper_nav.slideTo(1);

        getStepNumber(2);

        changeCount(count, 92);
        changeCount(count, 93);
        changeCount(count, 94);
        changeCount(count, 95);
        changeCount(count, 96);
    });

    for (let i = 1; i <= 4; i++) {
        $(`#person-${i}`).on('click', function(){
            $('label.active').removeClass('active');
            $(`${ROOT} .person:checked`).prop('checked', false);

            $(`input[name="${i}"]`).prop('checked', true);
            $(this).addClass('active');

            bookingStore.set({ person_count: i });
        });
    }

    function setPersonUI(count){
        for (let i = 1; i <= 4; i++) $(`#person-${i}`).removeClass('active');

        $(`#person-${count}`).addClass('active').find('input.person').prop('checked', true);

        $('.person-count').each(function(){
            $(this).html(count).attr('value', count);
        });

        $('#count-people .count').html(count > 1 ? (count + ' Personen') : (count + ' Person'));
    }
});