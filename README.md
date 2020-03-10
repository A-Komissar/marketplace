# Marketplace

### Set Up

- Install [PHP](http://fi2.php.net/downloads.php) and one of the following Databases: [MySQL](https://www.mysql.com/downloads/), [PostgreSQL](https://www.postgresql.org/download/), [MS SQL Server](https://www.microsoft.com/en-us/sql-server/sql-server-downloads) or [SQL Lite](https://www.sqlite.org/download.html).
- Install [Composer](https://getcomposer.org/).
- Run `composer install` to install dependencies.
- Create file *.env* (copy it from *.env.example*) and set your DB connections: *DB_CONNECTION* (mysql, pgsql, sqlsrv, sqlite), *DB_DATABASE*, *DB_PORT*, *DB_USERNAME*, *DB_PASSWORD*. 
- For email sending make sure that you have in your *.env* file next keys set: *MAIL_DRIVER*, *MAIL_HOST*, *MAIL_PORT*, *MAIL_USERNAME*, *MAIL_PASSWORD*, *MAIL_ENCRYPTION*, *CONTACT_EMAIL*, *MAIL_FROM_NAME*. 
- For integration with Rozetka set next keys in your *.env* file: *ROZETKA_USERNAME*, *ROZETKA_PASSWORD*, *ROZETKA_TITLE*, *ROZETKA_COMPANY*. 
- For Google ReCaptcha set next keys in your *.env* file: *RECAPTCHA_KEY*, *RECAPTCHA_SECRET*. 
- Fou production build change environment to production in your *.env* file: *APP_ENV=production*.
- Import DB with initiad data from *database/backups* folder.
- To update your DB to current version run `php artisan migrate`. If you want to rollback old migration use `php artisan migrate:rollback`.
- Run `php artisan key:generate` to generate app key. If you get any error on key generation, check if line *APP_KEY=* exists in *.env*, then rerun command.
- Run `npm run dev` or `npm run prod` to build your *app.css* and *app.js* in public folder from files in resources folder.
- To clear your config cache run `php artisan config:clear`.
- To start WebSockets server run `php artisan websockets:serve`. Make sure you have right broadcast driver (*BROADCAST_DRIVER=pusher*) and right *APP_URL* (in your *.env*).

### Possible Exceptions

- If you get **cURL error 60: SSL certificate: unable to get local issuer certificate** download [cacert.pem](https://curl.haxx.se/docs/caextract.html) and save, then in your *php.ini* put location of this file: `curl.cainfo = "C:\xampp\php\extras\ssl\cacert.pem"`
- If you get **Maximum execution time of 120 seconds exceeded** exception open *php.ini* and set `max_execution_time = 99999`