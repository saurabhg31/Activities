<form action="operation/imagesAdd" method="POST" onsubmit="submitFormData($(this)); return false;">
    @csrf
    <div class="form-inline">
        <div class="col-sm-4">
            <input type="file" name="images[]" class="form-control" multiple style="margin-top: -25%;margin-left:-18%;" onchange="listFileNames($(this));">
        </div>
        <div class="col-sm-8">
            <label for="fileListOutput" style="float: left;">File list:</label><br>
            <div id="fileListOutput" style="height: 200px; overflow-y: auto; background: lightgrey;"></div>
        </div>
    </div>
    <div class="col-sm-4">
        <select name="domain" class="form-control" style="margin-top: -19%;">
            <option value="public">Public</option>
            <option value="private" @if(Session::has('domain') && Session::get('domain') === 'private') selected @endif>Private</option>
        </select>
        <div class="form-inline">
            <select name="type" id="typeSelect" class="form-control" style="width:78%;">
                <option value="">Select image type</option>
                @foreach ($data['types'] as $type)
                    <option value="{{$type->type}}" @if(isset($data['selectedType']) && $data['selectedType'] === $type->type) selected @endif>{{$type->type}}</option>
                @endforeach
            </select>
            <button id="addNewType" class="btn btn-success">Add New</button>
        </div>
        <br>
        <textarea name="tags" id="imageTags" class="form-control" rows="3" cols="4" placeholder="Add tags to this/these image/images. eg: #cats, #nature etc." style="margin-top: -47%; margin-bottom: 12%; height: 50%;"></textarea>
    </div>
    <div class="col-sm-12" style="float: center; margin-top: 6%;">
        <div class="form-inline">
            <div class="col-sm-4">
                <button class="btn btn-primary" type="button" onclick="toggleDomain()">Switch domain</button>
            </div>
            <div class="col-sm-4">
                <input id="domain" value="Domain: {{Session::has('domain') ? strtoupper(Session::get('domain')) : 'PUBLIC'}}" class="form-control" disabled>
                <button class="btn btn-success" id="switchDomainButt" type="button" onclick="toggleDomain($(this).prev().val(), $(this))" style="display:none;">Switch</button>
            </div>
            <div class="col-sm-4">
                <button type="submit" class="btn btn-success" style="margin-top: 3%;">Upload Images</button>
            </div>
        </div>
    </div>
</form>
@if(isset($data['images']))
<legend>Displaying <label id="imageCount">{{count($data['images'])}}</label> of {{$data['images']->total()}} results. Page {{$data['images']->currentPage()}} of {{$data['images']->lastPage()}}</legend>
@php($count = 1)
@foreach ($data['images'] as $image)
    @if($count === 1 || $count === 5)
    <div class="form-inline">
    @endif
        <div class="col-sm-3">
            <img src="data:image/{{$image->imageType}};base64, {{$image->image}}" title="Type: {{$image->type}} || Tags: {{$image->tags}}" style="max-height: 250px; max-width: 222px; cursor: pointer;" onclick="openImageInModal($(this))"/><br>
            <label>Uploaded on: {{gmdate('d M, Y h:i a', strtotime($image->created_at)+19800)}}</label>
            <button class="btn btn-danger" onclick="removeImage({{$image->id}}, $(this).parent())">Delete</button>
        </div>
    @php($count++)
    @if($count === 5)
    </div><br>
    @php($count = 1)
    @endif
@endforeach
@endif
<div style="margin-left: 1%;">{{$data['images']->links()}}</div>