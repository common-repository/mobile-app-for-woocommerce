jQuery(document).ready(function ($){

    const admin_bar = $('#wpadminbar');

    // const header = $('header');

    if(admin_bar.length) {
        $('body').css('margin-top',`${-1 * admin_bar.height()}px`)
        admin_bar.remove();
    }

    // if(header.length)
    //     header.remove();
});