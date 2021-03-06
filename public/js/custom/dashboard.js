let display, imgEdit;

$(document).ready(function () {
    toastr.options.escapeHtml = true;
    toastr.info('Welcome to Activity Manager');
    console.clear();
    display = {
        output: $('.loader'),
        heading: $('.loaderHeading'),
        parent: $('.loader').parent()
    };
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
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
                    submitButton.html(percentComplete+' % uploaded');
                }
                if(percentComplete === 100){
                    submitButton.html('Uploaded. Processing data...');
                }
            }, false );
            return jqXHR;
        },
        beforeSend: function () {
            if (callables && callables.beforeSend) {
                callables.beforeSend();
            }
            if(submitButton){
                submitButton.attr('disabled', true);
                submitButton.html('Processing...');
            }
        },
        success: function (response) {
            if (callables && callables.success) {
                callables.success(response);
            }
            if(response.html){
                display.output.html(null);
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
                submitButton.attr('disabled', false);
            }
        }
    });
}

function submitFormData(form){
    transmitData(form.attr('action'), form.attr('method'), new FormData(form[0]), form.find('button[type="submit"]'));
    return false;
}

function toggleDomain(key = null, button = $('#switchDomainButt')){
    let domainField = $('#domain');
    if(!key){
        domainField.attr('type', 'password').attr('disabled', false).attr('placeholder', 'Enter account password & press change domain ').css('width', '100%').val(null);
        button.show('fast');
    }else{
        $.post(APP_URL+'switchDomain', {password: key}, function(response){
            toastr.info('Switched domain. Entering private space...');
            domainField.val('Domain: '+response);
            $('#imagesAdd').trigger('click');
        }).fail(function(){
            domainField.val('Domain: PUBLIC').attr('type', 'text').attr('disabled', true).attr('style', null);
            button.hide('fast');
        }).done(function(){
            domainField.val('Domain: PUBLIC').attr('type', 'password').attr('disabled', true).attr('style', null);
            button.hide('fast');
        });
    }
}

function removeImage(imageId, imageParagraph){
    if(!confirm('Are you sure you want to delete this image from database?')){
        return false;
    }
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

function openImageInModal(image){
    let imageHtml = '<img src="'+image.attr('src')+'" title="'+image.attr('title')+'" style="max-width: 100%; max-height: 100%;"/>';
    let modal = $('#myModal');
    modal.find('div[class="modal-body"]').html(imageHtml);
    modal.modal('show');
}

/**
 * BytesConverter from https://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
 * @param {Integer} bytes 
 * @param {Integer} decimals 
 */
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function listFileNames(input){
    let output = $('#fileListOutput');
    let file, totalBytes = 0;
    output.html(null);
    for (var i = 0; i < input.get(0).files.length; ++i) {
        file = input.get(0).files[i];
        output.html(output.html()+'<li>Name: '+file.name+',&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Size: '+formatBytes(file.size)+'</li>');
        totalBytes += file.size
    }
    output.append('<p style="margin-top: 2%;"><b>Total size: '+formatBytes(totalBytes)+'</b></p>');
}

function editImage(imageId, img){
    imgEdit = img;
    let imageHtml = '<img src="'+img.attr('src')+'" title="'+img.attr('title')+'" style="max-width: 100%; max-height: 300px;"/>';
    $.get(APP_URL+'getImageEditForm', {imageId: imageId}, function(response){
        let modal = $('#myModal');
        modal.find('div[class="modal-body"]').html(imageHtml+response);
        modal.modal('show');
    });
}

function postEditImageInfo(form){
    transmitData(form.attr('action'), form.attr('method'), new FormData(form[0]), form.find('button[type="submit"]'), {success: function(){
        $('#myModal').modal('hide');
        imgEdit.attr('title', 'Type: '+form.find('select[name="type"]').val()+' || Tags: '+form.find('input[name="tags"]').val());
        imgEdit = null;
    }});
    return false;
}

$(document).on('click', '#expenses,#reminders,#aps,#travelLogs,#marketing,#imagesAdd,#truncateWallpapers,#searchImages,#addNewType', function (e) {
    buttonHtml = $(this).html();
    if(this.id === 'addNewType'){
        e.preventDefault();
        let newType = prompt("Enter type:");
        $("#typeSelect").append(new Option(newType, newType));
        $("#typeSelect").val(newType);
    }
    else if(this.id === 'truncateWallpapers'){
        $(this).html('Processing...');
        button = $(this);
        let proceedPass = prompt('Are you sure to delete all images ? Enter password to confirm');
        if(proceedPass){
            $.post(APP_URL+'authorizeCriticalOperation', {password: proceedPass}, function(){
                toastr.success('Authorized. Proceeding...');
                return transmitData('operation/' + button.attr('id'));
            }).fail(function(response){
                if(response.status === 403){
                    toastr.error('Not Authorized.');
                    button.html(buttonHtml);
                    return false;
                }
            }).done(function(){
                button.html(buttonHtml);
            });
        }
        else{
            $(this).html(buttonHtml);
        }
    }
    else{
        return transmitData('operation/' + this.id, 'GET', null, $(this));
    }
});

/* TODO: Solve hosting issue, url unauthorized response in production */
function renderUrl(inputUrl){
    if(inputUrl.includes(APP_URL)){
        return inputUrl.replace(APP_URL, '');
    }
    else{
        if(inputUrl.includes('http://') && APP_URL.includes('https://')){
            return inputUrl.replace('http://', 'https://');
        }
    }
}

$(document).on('click', '.page-link', function(event){
    event.preventDefault();
    if($(this).attr('href').includes('operation/searchImages')){
        let form = $('#searchImagesForm');
        return transmitData(
            renderUrl($(this).attr('href')),
            form.attr('method'), new FormData(form[0]),
            form.find('button[type="submit"]'),
            {success: function(){
                display.output.scrollTop(45);
            }}
        );
    }
    else{
        return transmitData($(this).attr('href').split(APP_URL).pop(), 'GET', null, null, {
            success: function(){
                display.output.scrollTop(250);
            }
        });
    }
});