<div class="form-group">
    <legend>Smoking Counter</legend>
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
                <button class="btn btn-primary" style="margin-top:8%;" type="submit">Add Smoking Event</button>
            </div>
        </div>
    </form>
</div>
<div class="form-group">
    <legend>Smoking Counter History (Each entry represents a smoking event, total: {{ number_format($data['totalCount']) }})</legend>
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