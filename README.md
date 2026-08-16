# Laravel Application Setup

A Laravel 9 application configured for heavy image processing, compression, duplicate image detection, image tagging, searching images based on tags, smoking habit tracker using linear regression to reach a target goal by a certain target date (values set in config/constants.php)

---

### Steps to Run

1. **Requires PHP 8.3**
2. **`phpImagick` extension is required**
3. **`composer` is required**
4. **Run `composer update`:**
   ```bash
   composer update
5. **Install MySql 8.0 or higher**
6. **Set .env variables**
7. **Run "php artisan migrate":**
    ```bash
    php artisan migrate
8. **Update your php.ini file to allow uploads of number of files you want, post_max_size, etc.**
9. **Update your php.ini file to allow 5GB RAM for php process (recommended for heavy image compression)**
10. **Update your php.ini file to allow time limit of 5-10 minutes, if retrieving a lot of images, may take a while based on your hardware.**
11. **To host run: "php artisan serve":**
    ```bash
    php artisan serve
12. **Queue running is mandatory, run "php artisan queue:listen --timeout=300 --memory=512" (timeout & memory flags are required for image compression & duplicate image detection):**
    ```bash
    php artisan queue:listen --timeout=300 --memory=512
13. **Enjoy**

### NOTE: The app's timezone is set to "Asia/Kolkata", to update add APP_TIMEZONE & it's respective value to .env file
