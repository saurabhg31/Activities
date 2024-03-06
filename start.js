const LOG_ERRORS_ONLY = false;
const { exec } = require('child_process');

function executeCommand(command) {
  exec(command.toString(), (error, stdout, stderr) => {
    if (error) {
      console.error(`Error: ${error.message}`);
      return;
    }
    if (stderr) {
      console.error(`stderr: ${stderr}`);
      return;
    }
    if (!LOG_ERRORS_ONLY) {
      console.log(`stdout:\n${stdout}`);
    }
    return true;
  });
}

executeCommand('service mysql start');
executeCommand('php artisan serve');
executeCommand('php artisan queue:work');
executeCommand('php artisan schedule:work');

console.log('Server started at: http://localhost:8000/');