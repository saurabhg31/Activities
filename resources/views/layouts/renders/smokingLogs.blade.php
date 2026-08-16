<div class="form-group">
    <legend>Smoking weekly logs for this month</legend>
    <table class="table table-bordered table-striped" id="smokingCounterTable">
        <thead>
            <tr>
                <th>Week No.</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            @php($weekNo = count($data['logs']))
            @foreach ($data['logs'] as $log)
            <tr>
                <td>{{$weekNo}}</td>
                <td>{{ $log['week_start_date'] }}</td>
                <td>{{ $log['week_end_date'] }}</td>
                <td>{{ number_format($log['total_cigarettes']) }}</td>
            </tr>
            @php($weekNo--)
            @endforeach
        </tbody>
    </table>