@if (isset($data['images']))
    <legend style="margin-top: 0.4%;">
        Displaying <label id="imageCount">{{ count($data['images']) }}</label> of
        {{ number_format($data['images']->total()) }} possible duplicates. Page {{ $data['images']->currentPage() }} of
        {{ $data['images']->lastPage() }}
    </legend>
    @php($count = 1)
    @php($images = \App\Models\Images::whereIn('id', $data['images']->pluck('id'))->get())
    @foreach ($images as $image)
        @if (!isset($image->imageType))
            @continue
        @endif
        @if ($count === 1 || $count === 5)
            <div class="form-inline">
        @endif
        <div class="col-sm-3">
            <img src="{{\App\Helpers\getBase64StringFromImageData($image)}}"
                title="Type: {{ $image->type }} || Tags: {{ $image->tags }}"
                style="max-width: 100%; max-height: 100%; cursor: pointer;" onclick="openImageInModal($(this))" /><br>
            <label>Image Id: {{ $image->id }} : {{ $image->created_at->format('Y\/m\/d h:i a') }}</label>
            <button type="button" class="btn btn-warning"
                onclick="editImage({{ $image->id }}, $(this).prev().prev().prev())">Edit</button>
            @if (!Session::has('domain') || strtolower(Session::get('domain')) == 'public')
                <button class="btn btn-success" onclick="setImageAsWallpaper({{ $image->id }}, $(this))">Set As
                    Wallpaper</button>
            @endif
            <button class="btn btn-danger" onclick="removeImage({{ $image->id }}, $(this).parent())">Delete</button>
        </div>
        @php($count++)
        @if ($count === 5)
            </div><br>
            @php($count = 1)
        @endif
    @endforeach
@endif
<div style="margin-left:1%;margin-right:1%;">{{ $data['images']->links('pagination::bootstrap-5') }}</div>
