let display;

$(document).ready(function () {
    toastr.options.escapeHtml = true;
    toastr.info('Welcome to Activity Manager');
    console.clear();
    display = {
        output: $('.loader'),
        heading: $('.loaderHeading'),
        parent: $('.loader').parent()
    };
});

function transmitData(uri, requestType = 'GET', data = null, submitButton = null, callables = null, dataType = 'json') {
    if(submitButton){
        submitButtonHtml = submitButton.html();
    }
    $.ajax({
        url: APP_URL + uri,
        data: data ? data : null,
        dataType: dataType,
        processData: false,
        contentType: false,
        type: requestType,
        xhr: function ()
        {
            var jqXHR = null;
            if ( window.ActiveXObject )
                jqXHR = new window.ActiveXObject( "Microsoft.XMLHTTP" );
            else
                jqXHR = new window.XMLHttpRequest();
            jqXHR.upload.addEventListener( "progress", function ( evt )
            {
                if (evt.lengthComputable)
                {
                    var percentComplete = Math.round((evt.loaded*100)/evt.total);
                    submitButton.html(percentComplete+' % uploaded')
                }
            }, false );
            return jqXHR;
        },
        beforeSend: function () {
            if (callables && callables.beforeSend) {
                callables.beforeSend();
            }
        },
        success: function (response) {
            if (callables && callables.success) {
                callables.success(response);
            }
            display.output.html(null);
            if(response.html){
                display.output.html(response.html);
            }
            if(response.data){
                display.output.append('<legend>------------DATA----------------</legend>')
                display.output.append(response.data);
            }
            if(response.msg){
                let msg = response.msg;
                if(typeof msg[0] === 'undefined'){
                    if(msg.heading){
                        display.heading.html(msg.heading);
                    }
                    if(msg.text){
                        toastr.success(msg.text);
                    }
                }
                else{
                    display.parent.html(null);
                    console.log(msg);
                    $.each(msg, function(index, message){
                        display.parent.append('<div class="card text-center" style="margin-top: 2%;"> <div class="card-header text-center loaderHeading">'+(message.heading ? message.heading : 'Display')+'</div> <div class="custom-block text-center loader" style="max-height: 308px; max-width: 728px; overflow:auto;">'+(message.html ? message.html : '<legend>Dynamic Interactive Screen</legend>')+'</div> </div>');
                        if(message.text){
                            toastr.success(msg.text);
                        }
                    });
                }
            }
        },
        error: function (error, status) {
            if (callables && callables.error) {
                callables.error(error, status);
            }
            if(error.status === 422){
                if(error.responseJSON){
                    if(error.responseJSON.data){
                        $.each(error.responseJSON.data, function(field, msgs){
                            $.each(msgs, function(index, msg){
                                toastr.error(msg, 'Validation error: '+field+' -> '+(index+1));
                            });
                        });
                    }
                }
            }
            else {
                toastr.error(status, 'Data transmission error');
                console.log(error, status);
            }
        },
        complete: function () {
            if (callables && callables.complete) {
                callables.complete();
            }
            if(submitButton){
                submitButton.html(submitButtonHtml);
            }
        }
    });
}

function submitFormData(form){
    transmitData(form.attr('action'), form.attr('method'), new FormData(form[0]), form.find('button[type="submit"]'));
    return false;
}

function removeImage(imageId, imageParagraph){
    $.get(APP_URL+'removeImage?imageId='+imageId, function(response){
        if(response.data){
            imageParagraph.remove();
            imageCount = parseInt($('#imageCount').html());
            $('#imageCount').html(imageCount-1);
            toastr.info('Image deleted');
        }
        else{
            toastr.error('Unable to delete image!');
        }
    });
}

$(document).on('click', '#expenses,#reminders,#aps,#travelLogs,#marketing,#imagesAdd,#truncateWallpapers', function (e) {
    return transmitData('operation/' + this.id);
});