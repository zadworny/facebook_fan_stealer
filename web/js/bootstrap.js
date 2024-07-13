var dropdownHideTimeout = 150;

var menu = $('ul.navbar-menu');
var radioMenu = $('ul.radio');
var dropdownBoxes = $('.dropdown');
var tagsField = $('.tags-field');

$(function() {

    /* Radio Menu */
    radioMenu.find('li').on('click', function() {
        $(this).closest('ul').find('li').attr('data-checked', 0);
        $(this).attr('data-checked', 1);
    });
    /* /Radio Menu */

    /* Navbar Dropdowns */
    menu.find('li > a').on('mouseenter', function() {
        var div = $(this).closest('li').find('div');
        clearTimeout(div.data('timeout'));
        div.fadeIn();
    });

    menu.find('li > a').on('mouseleave', function() {
        var div = $(this).closest('li').find('div');
        var timeout = setTimeout(function() {
            div.fadeOut();
        }, dropdownHideTimeout);
        div.data('timeout',timeout);
    });

    dropdownBoxes.on('mouseenter', function() {
        clearTimeout($(this).data('timeout'));
    });

    dropdownBoxes.on('mouseleave', function() {
        var self = $(this);
        var timeout = setTimeout(function() {
            self.fadeOut();
        }, dropdownHideTimeout);
        self.data('timeout', timeout);
    });
    /* /Navbar Dropdowns */

    $('#settings').find('li').on('click', function() {
        var selected = $(this).data('value');

        $.ajax({
            type: 'POST',
            url: '/ajax/select_app',
            data: { value: selected }
        });
    });

    $('#settings').find('input[type="text"]').on('blur', function() {
        $.ajax({
            type: 'POST',
            url: '/ajax/app_settings',
            data: {
                app_id: $('#custom_app_id').val(),
                secret: $('#custom_app_secret').val()
            }
        })
    });

});

var afterDelay = (function() {
    var timer = 0;
    return function(callback, ms){
        clearTimeout (timer);
        timer = setTimeout(callback, ms);
    };
})();
