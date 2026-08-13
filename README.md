Steps to run it:

1. Requires php 8.3
2. phpImagick is required
3. composer is required
4. run "composer update"
5. Set .env variables
6. run "php artisan migrate"
7. Update your php.ini file to allow uploads of number of files you want, post_max_size, etc.
8. Update your php.ini file to allow 5GB RAM for php process (recommended for heavy image compression)
9. Update your php.ini file to allow time limit of 5-10 minutes, if retrieving a lot of images, may take a while based on your hardware.
8. To host run: "php artisan serve"
9. Queue running is mandatory, run "php artisan queue:listen --timeout=300 --memory=512" (timeout & memory flags are required for image compression)
10. Enjoy