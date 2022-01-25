function send_statistic(elem){
    if (elem.length) {
        var yaid = elem.data('yaid'),
            yaevent = elem.data('yaevent'),
            goevent = elem.data('goevent');
        if (typeof ('yaCounter'+yaid) !== 'undefined' && typeof yaevent !== 'undefined' && yaevent!='') {
            var yaevent = yaevent.split(';')
            window['yaCounter'+ yaid].reachGoal(yaevent[1]);
            console.log('YaID:'+yaid,'Event:'+yaevent[1]);
        }
        if (typeof goevent !== 'undefined' && goevent!='') {
            var goevent = goevent.split(';');
            gtag("event", "RegionEvent", { "event_category": goevent[0], "event_action": goevent[1] });
            console.log('GOOGLE: '+goevent[0]+' - '+goevent[1]);
        }
    }
}

$('.btn-ya-go').on('click',function(){
    var element = $(this);

    send_statistic(element);
});

// В верстке для обработки данных у необходимых элементов указываем доп. параметры, которые и будут передаваться в скрипт Например: <a data-goevent="form_to_buy1cl;send" data-yaevent="open_click;send_click" data-yaid="47516515">Click</a>
 
