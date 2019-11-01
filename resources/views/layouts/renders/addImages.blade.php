<form action="operation/imagesAdd" method="POST" enctype="multipart/form-data" onsubmit="submitFormData($(this)); return false;">
    @csrf
    <div class="form-inline">
        <div class="col-sm-4">
            <input type="file" name="images[]" class="form-control" multiple style="margin-top: -18%;" onchange="listFileNames($(this));">
        </div>
        <div class="col-sm-8">
            <label for="fileListOutput" style="float: left;">File list:</label><br>
            <div id="fileListOutput" style="height: 200px; overflow-y: auto; background: lightgrey;"></div>
        </div>
    </div>
    <div class="col-sm-4">
        <label for="imageTags" style="float: left; margin-top: -8%;">Tags:</label>
        <textarea name="tags" id="imageTags" class="form-control" rows="3" cols="4" placeholder="Add tags to this/these image/images" style="margin-top: -110.3125px; margin-bottom: 0px; height: 110px;"></textarea>
    </div>
    <div class="col-sm-12" style="float: center; margin-top: 2%;">
        <button type="submit" class="btn btn-success">Upload Images</button>
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
            <img src="data:image/{{$image->imageType}};base64, {{$image->image}}" title="Type: {{$image->type}} || Tags: {{$image->tags}}" style="height: 250px; cursor: pointer;" onclick="openImageInModal($(this))"/><br>
            <label>Uploaded on: {{gmdate('d M, Y h:i a', strtotime($image->created_at))}}</label>
            <button class="btn btn-danger" onclick="removeImage({{$image->id}}, $(this).parent())">Delete</button>
        </div>
    @php($count++)
    @if($count === 5)
    </div><br>
    @php($count = 1)
    @endif
@endforeach
@endif
<div style="margin-left: 43%;">{{$data['images']->links()}}</div>