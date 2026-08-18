<div class="form-group">
    <br>
    <form method="POST" action="operation/smokeCounter" onsubmit="submitFormData($(this)); return false;">
        @csrf
        <div class="row col-sm-12">
            <div class="col-sm-4">
                <label style="float:left;" for="smokingCount">Current Smoking Count: (Date: {{ now()->format('d-M-Y') }})</label>
                <input class="form-control" type="number" value="{{number_format($data['currentCount'])}}" placeholder="Enter current smoking count" id="smokingCount" min="0" max="1000" disabled />
            </div>
            <div class="col-sm-4">
                <label style="float:left;" for="cigaretteName">Cigarette:</label>
                <input class="form-control" id="cigaretteName" type="text" name="cigarette_name" placeholder="Enter cigarette name" style="width: 125%;" />
            </div>
            <div class="col-sm-4">
                <input type="hidden" name="increment" value="1" />
                <button class="btn btn-warning" style="margin-top:8%;" type="submit">Add Smoking Event</button>
            </div>
        </div>
    </form>
</div>
<div class="form-group">
    <legend>History / Day (T: {{ number_format($data['totalCount']) }}, F/D: @if(is_null($data['frequency'])) N/A @else 1 / {{ $data['frequency'] }} @endif, PDC: {{ number_format($data['previousDatCount']) }}, DBL2C: {{ $data['dbl2c'] }}, TR: <span style="color: {{$data['trend']['color']}};">{{$data['trend']['status']}}</span>, @if(isset($data['trend']['bingeResetTime'])) BRT: {{$data['trend']['bingeResetTime']}} @else WT: {{$data['trend']['waitTime']}} @endif)
    </legend>
    <table class="table table-bordered table-striped" id="smokingCounterTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Cigarette Name</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['list'] as $entry)
            <tr>
                <td>{{ $entry->created_at->format('d-M-Y H:i:s') }}</td>
                <td>{{ $entry->cigarette_name }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>