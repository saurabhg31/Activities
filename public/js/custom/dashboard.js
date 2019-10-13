const display = { heading: $('#loaderHeading'), output: $('#loader') };

$(document).ready(function () {
    toastr.options.escapeHtml = true;
    toastr.info('Welcome to Activity Manager');
    console.clear();
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
            console.log(response);
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