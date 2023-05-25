<div class="form-group">
<legend>Add Expense</legend>
<form method="POST" action="operation/expenses" onsubmit="submitFormData($(this)); return false;">
@csrf
<div class="row col-sm-12">
<div class="col-sm-4">
<label style="float:left;" for="expName">Expense Name:</label>
<input class="form-control" type="text" name="name" placeholder="Give an expense name" id="expName" required/>
</div>
<div class="col-sm-4">
<label style="float:left;" for="expAmt">Expense Amount:</label>
<input class="form-control" type="number" name="amt" placeholder="Enter expense amount" id="expAmt" min="0.00" step="0.01" required/>
</div>
<div class="col-sm-4">
<label style="float:left;" for="expDate">Expense Date:</label>
<input class="form-control" type="date" name="date" placeholder="Enter expense date" id="expDate" value="{{$data['currentDate']}}" required/>
</div>
</div>
<div class="row col-sm-12" style="margin-top:1%; margin-left:0%;">
<label for="expDesc" style="float:left;">Description</label>
<textarea name="desc" id="expDesc" class="form-control" placeholder="Enter expense description (optional)" style="97.7%"></textarea>
</div>
<div class="row col-sm-12" style="margin-top:1%; margin-left:0%;">
<div class="col-sm-4">
<button class="btn btn-primary" type="submit">Add Expense</button>
</div>
<div class="col-sm-4">
<button class="btn btn-warning" type="reset">Reset Form</button>
</div>
</div>
</form>
</div>