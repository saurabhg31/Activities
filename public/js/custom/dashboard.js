let display;
$(document).ready(function () {
    toastr.options.escapeHtml = true;
    toastr.info('Welcome to Activity Manager');
    console.clear();
    display = { heading: $('#loaderHeading'), output: $('#loader') };
});

function transmitData(uri, requestType = 'GET', data = null, callables = null, dataType = 'json') {
    $.ajax({
        url: APP_URL + uri,
        data: data ? data : null,
        dataType: dataType,
        processData: false,
        contentType: false,
        type: requestType,
        beforeSend: function () {
            if (callables && callables.beforeSend) {
                callables.beforeSend();
            }
        },
        success: function (response) {
            if (callables && callables.success) {
                callables.success(response);
            }
            if(response.html){
                display.output.html(response.html);
            }
            if(response.msg){
                let msg = response.msg;
                if(msg.heading){
                    display.heading.html(msg.heading);
                }
                if(msg.text){
                    toastr.success(msg.text);
                }
            }
        },
        error: function (error, status) {
            if (callables && callables.error) {
                callables.error(error, status);
            }
            else {
                alert('Data transmission error => ' + error.status + ': ' + error.statusText);
                console.log(error, status);
            }
        },
        complete: function () {
            if (callables && callables.complete) {
                callables.complete();
            }
        }
    });
}

$(document).on('click', '#expenses,#reminders,#aps,#travelLogs,#marketing', function (e) {
    return transmitData('operation/' + this.id);
});