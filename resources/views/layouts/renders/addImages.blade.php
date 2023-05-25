<form action="operation/imagesAdd" method="POST" onsubmit="submitFormData($(this)); return false;">
    @csrf
    <div class="form-inline">
        <div class="col-sm-4">
            <input type="file" name="images[]" class="form-control" multiple style="margin-top:-33%;width:100%"
                onchange="listFileNames($(this));" id="addImagesInput" accept="image/*">
        </div>
        <div class="col-sm-8">
            <label for="fileListOutput" style="float: left;">File list:</label><br>
            <div id="fileListOutput" style="height: 158px; overflow-y: auto; background: lightgrey;"></div>
            <button type="submit" id="uploadImagesBtn" class="btn btn-primary" style="width:100%;margin-top:0.5%">Upload Images</button>
        </div>
    </div>
    <div class="col-sm-4">
        <select name="domain" class="form-control" style="margin-top:-16%;">
            <option value="public">Public</option>
            <option value="private" @if (Session::has('domain') && Session::get('domain') === 'private') selected @endif>Private</option>
        </select>
        <div class="form-inline">
            <select name="type" id="typeSelect" class="form-control" style="width:80.5%;" required>
                <option value="">Select image type</option>
                @foreach ($data['types'] as $type)
                    <option value="{{ $type->type }}" @if (isset($data['selectedType']) && $data['selectedType'] === $type->type) selected @endif>
                        {{ $type->type }}</option>
                @endforeach
            </select>
            <button id="addNewType" class="btn btn-success">Add New</button>
        </div>
        <br>
        <textarea name="tags" id="imageTags" class="form-control" rows="3" cols="4"
            placeholder="Add tags to this/these image/images. eg: #cats, #nature etc. For links: links> <-- link 1 -->, <-- link 2 -->, tags> <-- your tags -->"
            style="margin-top:-41%;height:50%;"></textarea>
    </div>
    <div class="col-sm-12" style="float: center; margin-top: 6%;"></div>
</form>
@if (isset($data['images']))
    <legend>
        Displaying <label id="imageCount">{{ count($data['images']) }}</label> of {{ $data['images']->total() }}
        results. Page {{ $data['images']->currentPage() }} of {{ $data['images']->lastPage() }}
    </legend>
    @php($count = 1)
    @foreach ($data['images'] as $image)
        @if ($count === 1 || $count === 5)
            <div class="form-inline">
        @endif
        <div class="col-sm-3">
            <img src="data:image/{{ $image->imageType }};base64, {{ $image->image }}"
                title="Type: {{ $image->type }} || Tags: {{ $image->tags }}"
                style="max-width: 100%; max-height: 100%; cursor: pointer;" onclick="openImageInModal($(this))" /><br>
            <label>Uploaded on: {{$image->created_at->format('d M, Y \a\t h:i:s a')}}</label>
            <button type="button" class="btn btn-warning"
                onclick="editImage({{ $image->id }}, $(this).prev().prev().prev())">Edit</button>
            <button type="button" class="btn btn-danger"
                onclick="removeImage({{ $image->id }}, $(this).parent())">Delete</button>
        </div>
        @php($count++)
        @if ($count === 5)
            </div><br>
            @php($count = 1)
        @endif
    @endforeach
@endif
<div style="margin-left:1%;margin-right:1%;">{{ $data['images']->links('pagination::bootstrap-5') }}</div>
