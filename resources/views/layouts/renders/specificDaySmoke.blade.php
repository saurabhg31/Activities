<div class="form-group">
    <br>
    <form method="POST" action="operation/specificDaySmoke" onsubmit="submitFormData($(this)); return false;">
        @csrf
        <div class="row col-sm-9">
            <div class="col-sm-4"></div>
            <div class="col-sm-4">
                <label for="smokeLogDate" style="float: left;">Logs for date:</label>
                <input class="form-control" name="date" type="date" value="{{$data['forDate']->format('Y-m-d')}}" placeholder="Enter date for which you want smoking logs" id="smokeLogDate" required />
            </div>
            <div class="col-sm-4">
                <button class="btn btn-success" style="margin-top:11.5%;" type="submit">Fetch Records</button>
            </div>
        </div>
    </form>
    <br>
    <legend>Smoking logs for {{ $data['forDate']->format('d M, Y') }} | Total: {{ number_format($data['smokeLogData']->count()) }}</legend>
    <table class="table table-bordered table-striped" id="daywiseSmokingLogsTable">
        <thead>
            <tr>
                <th>Index</th>
                <th>Date Time</th>
                <th>Cigarette name & description</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['smokeLogData'] as $index => $log)
                <tr>
                    <td>{{ number_format($index + 1) }}</td>
                    <td>{{ $log->created_at }}</td>
                    <td>{{ $log->cigarette_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>