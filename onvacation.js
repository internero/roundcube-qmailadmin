$(function () {
    if (!rcmail.env.framed) {
	$('BODY').append('<div id="onvacation" style="position: absolute; top: 5px; left: 220px; width: 500px; z-index: 2; border: 1px solid #da8080; background-color: #efdeed; padding: 2px 4px; text-align: center;">'+rcmail.gettext('vacationmessagealert', 'qmailadmin')+'. <a href="./?_task=settings">'+rcmail.gettext('change', 'qmailadmin')+'</a></div>');
    }

});