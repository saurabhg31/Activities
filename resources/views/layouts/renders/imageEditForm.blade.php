<form action="getImageEditForm" method="POST" onsubmit="postEditImageInfo($(this)); return false;">
@csrf
<br><select class="form-control" name="type">
@foreach($data['imageTypes'] as $type)
<option value="{{$type->type}}" @if($data['imageData']->type === $type->type) selected @endif>{{$type->type}}</option>
@endforeach
</select>
<input type="hidden" name="imageId" value="{{$data['imageData']->id}}"/><br>
<input type="text" name="tags" class="form-control" placeholder="Enter tags like #cat, #nature etc." value="{{$data['imageData']->tags}}"/><br>
<button class="btn btn-success" type="submit" style="float:right;">Update</button>
</form>