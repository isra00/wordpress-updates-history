Check for updates in multiple Wordpress instances hosted in the same server, and notify each owner by e-mail.

![Screenshot of an e-mail notification](http://israelviana.es/wp-content/uploads/2013/08/wordpress-updates-check.png)

Requirements
------------

 * Composer
 * MySQL user having read access to all Wordpress schemas, and a dedicated schema for storing metadata about updates.
 * An SMTP server for sending the notifications.
 * A server with cron, if you want to automate the checks ;-)

Installation
------------

 1. Clone the repo.
 2. Run composer install.
 3. Create a new MySQL schema and run the `create_tables.sql`.
 4. Edit the config section of `wp_updates.php` assigning values to the constants with your server's configuration (MySQL and SMTP credentials).
 5. Fill the table `sites` with all the Wordpress sites that you want to check.
 6. Set up a daily cron like `0 23 * * * php /your-path-to-cloned-repo/wp_updates.php`. In this example, the cron job will run each day on 23:00.

Before waiting the cron to run, you can manually run the checks in the command line: `php /your-path-to-cloned-repo/wp_updates.php`