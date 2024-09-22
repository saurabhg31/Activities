const LOG_ERRORS_ONLY = false;
const CHECK_MYSQL_SERVICE = true;
const { exec } = require('child_process');
const { exit } = require('process');

checkMySqlServiceStatus();
executeCommand('php artisan serve');
executeCommand('php artisan queue:work');
executeCommand('php artisan schedule:work');

console.log('\n Server started at: http://localhost:8000/ \n');

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

async function checkMySqlServiceStatus() {
  if (CHECK_MYSQL_SERVICE) {
    console.log('Checking mysql service ... ');
    exec('mysql', (error, stdout, stderr) => {
      if (stderr.includes("Can't connect to local MySQL server through socket") || stderr.includes("Can't connect to MySQL server on")) {
        // console.log('MySQL service inactive, restarting (may require `sudo` access) ... ');
        console.log('MySQL service inactive, exiting ... ');
        exit(1);
        // executeCommand('service mysql start');
      } else {
        console.log('DONE.\n');
      }
    });
  }
}
