<form action="operation/searchImages" method="POST" enctype="multipart/form-data" onsubmit="submitFormData($(this)); return false;">
    @csrf
    <div class="form-inline" style="margin-top: 2%;">
        <div class="col-sm-4">
            <select class="form-control" name="types" style="width: 100%;">
                <option value="">Select image type</option>
                @foreach ($data['types'] as $type)
                    <option value="{{$type->type}}" @if(isset($data['selectedType']) && $data['selectedType'] === $type->type) selected @endif>{{$type->type}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4">
            <input name="tags" type="text" class="form-control" style="width: 100%;" placeholder="Enter tags separated by ,. (#nature, #constancenunes etc.)" value="{{@$data['selectedTags']}}"/>
        </div>
        <div class="col-sm-4">
            <button type="submit" class="btn btn-success" style="width: 100%;">Search</button>
        </div>
    </div>
</form>
@if(isset($data['search']))
<legend>Displaying <label id="imageCount">{{count($data['search'])}}</label> of {{$data['search']->total()}} results. Page {{$data['search']->currentPage()}} of {{$data['search']->lastPage()}}</legend>
@php($count = 1)
@foreach ($data['search'] as $image)
    @if($count === 1 || $count === 5)
    <div class="form-inline">
    @endif
        <div class="col-sm-3">
            <img src="data:image/{{$image->imageType}};base64, {{$image->image}}" title="Type: {{$image->type}} || Tags: {{$image->tags}}" style="height: 250px; cursor: pointer;" onclick="openImageInModal($(this))"/><br>
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
<div style="margin-left: 1%;">{{$data['search']->links()}}</div>