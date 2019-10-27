<form action="{{env('APP_URL')}}operation/imagesAdd" method="POST" enctype="multipart/form-data">
    <input type="file" name="images[]" multiple>
    <button type="submit" class="btn btn-success">Upload Images</button>
</form>