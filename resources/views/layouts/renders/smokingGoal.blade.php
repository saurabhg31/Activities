<div class="form-group">
    <br>
    <form method="POST" action="operation/setSmokeGoal" onsubmit="submitFormData($(this)); return false;">
        @csrf
        <div class="row col-sm-12">
            <div class="col-sm-4">
                <label style="float:left;" for="smokeCount">Current Smoking Count Goal:</label>
                <input class="form-control" name="goal_count" type="number" value="{{$data['currentData']->goal_count ?? 0}}" placeholder="Enter current smoke count goal" id="smokeCount" min="0" step="1" required />
            </div>
            <div class="col-sm-4">
                <label style="float:left;" for="smokeGoalDate">Current Goal Reach By Date:</label>
                <input class="form-control" id="smokeGoalDate" type="date" name="goal_reach_date" placeholder="Enter cigarette smoke goal date" style="width: 125%;" value="{{$data['currentData']->goal_reach_date ?? null}}" required />
            </div>
            <div class="col-sm-4">
                <button class="btn btn-success" style="margin-top:8%;" type="submit">Add/Update Smoking Goal</button>
            </div>
        </div>
    </form>
</div>