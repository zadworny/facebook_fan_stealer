Fanstealer X
============
Based on Silex Framework

Installation
============
1. Upload files to server.
2. The public folder is /web, so map the domain to /web.
3. Create database with user and password.
4. Import schema.sql to database
5. Configure the application (app/config.php)
    * Use debug = false in production
    * Configure db.options for database
    * Configure fb.options for default app
6. Configure cron
    * with access to terminal: `crontab -e` and write the following:
        `* * * * * /full/path/to/bin/cron.php > /dev/null`
    * without access to terminal: the hosting may have option to run cron jobs. If not, you should contact support.
