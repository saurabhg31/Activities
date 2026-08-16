<div class="form-group">
    <legend>Smoking weekly logs for this month</legend>
    <table class="table table-bordered table-striped" id="smokingCounterTable">
        <thead>
            <tr>
                <th>Week No.</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th title="Difference between current & start date (including both)">Days Count</th>
                <th>Count</th>
                <th>Rate Per Day</th>
            </tr>
        </thead>
        <tbody>
            @php($weekNo = count($data['logs']))
            @foreach ($data['logs'] as $log)
                @php($currentDayCount = now()->diffInDays(\Illuminate\Support\Carbon::parse($log['week_start_date'])) + 1)
                <tr>
                    <td>{{$weekNo}}</td>
                    <td>{{ $log['week_start_date'] }}</td>
                    <td>{{ $log['week_end_date'] }}</td>
                    <td>{{ $currentDayCount }}</td>
                    <td>{{ number_format($log['total_cigarettes']) }}</td>
                    <td>{{ number_format(($log['total_cigarettes'] / $currentDayCount), decimals: 2) }}</td>
                </tr>
                @php($weekNo--)
            @endforeach
        </tbody>
    </table>