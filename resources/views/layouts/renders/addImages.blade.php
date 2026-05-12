<form action="operation/imagesAdd" method="POST" onsubmit="submitFormData($(this)); return false;">
    @csrf
    <div class="form-inline">
        <div class="col-sm-4" style="margin-top: -10%;">
            <input type="file" name="images[]" class="form-control" multiple onchange="listFileNames($(this));"
                id="addImagesInput" accept="image/*" style="width: 100%;" required>
        </div>
        <div class="col-sm-8">
            <label for="fileListOutput" style="float: left;">File list:</label><br>
            <div id="fileListOutput" style="height: 158px; overflow-y: auto; background: lightgrey;"></div>
            <button type="submit" id="uploadImagesBtn" class="btn btn-primary"
                style="width:100%;margin-top:0.5%">Upload Images</button>
        </div>
        <div class="col-sm-4" style="margin-top: -10%;">
            <textarea name="tags" id="imageTags" class="form-control" rows="3" cols="4"
                placeholder="Add tags to this/these image/images. eg: #cats, #nature etc. For links: links> <-- link 1 -->, <-- link 2 -->, tags> <-- your tags -->"
                style="width: 100%;"></textarea>
            <select name="domain" class="form-control" style="width: 100%; margin-top: 1.4%;">
                <option value="public">Public</option>
                <option value="private" @if (Session::has('domain') && Session::get('domain') === 'private') selected @endif>Private</option>
            </select>
            <div class="form-inline" style="width: 100%; margin-top: 1.9%;">
                <select name="type" id="typeSelect" class="form-control" required style="width: 82%;">
                    <option value="">Select image type</option>
                    @foreach ($data['types'] as $type)
                        <option value="{{ $type->type }}" @if (isset($data['selectedType']) && $data['selectedType'] === $type->type) selected @endif>
                            {{ $type->type }}</option>
                    @endforeach
                </select>
                <button id="addNewType" class="btn btn-success" style="margin-left: 2%;">Add New</button>
            </div>
            <br>
        </div>
    </div>
</form>
@if (isset($data['images']))
    <legend style="margin-top: -0.7%;">
        Displaying <label id="imageCount">{{ count($data['images']) }}</label> of
        {{ number_format($data['images']->total()) }}
        results. Page {{ $data['images']->currentPage() }} of {{ $data['images']->lastPage() }}
    </legend>
    @php($count = 1)
    @php($imageIds = $data['images']->pluck('id'))
    @php($images = \App\Models\Images::whereIn('id', $imageIds)->orderBy('id', 'desc')->get())
    @foreach ($images as $image)
        @if ($count === 1 || $count === 5)
            <div class="form-inline">
        @endif
        <div class="col-sm-3">
            <img src="{{ \App\Helpers\getBase64StringFromImageData($image) }}"
                title="Type: {{ $image->type }} || Tags: {{ $image->tags }}"
                style="max-width: 100%; max-height: 100%; cursor: pointer;" onclick="openImageInModal($(this))" /><br>
            <label>Uploaded on: {{ $image->created_at->format('d M, Y \a\t h:i:s a') }}</label>
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
