<form action="operation/imagesAdd" method="POST" enctype="multipart/form-data" onsubmit="submitFormData($(this)); return false;">
    @csrf
    <input type="file" name="images[]" multiple>
    <button type="submit" class="btn btn-success">Upload Images</button>
</form>
@if(isset($data['images']))
<legend>Total images: <label id="imageCount">{{count($data['images'])}}</label></legend>
@foreach ($data['images'] as $image)
    <p>
        <img src="data:image/{{$image->imageType}};base64, {{$image->image}}"/><br>
        <button class="btn btn-danger" onclick="removeImage({{$image->id}}, $(this).parent())">Delete</button>
    </p>
@endforeach
@endif