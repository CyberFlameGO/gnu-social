TABLE OF CONTENTS
=================
* Prerequisites
    - PHP modules
    - Better performance
* Installation
    - Getting it up and running
    - Fancy URLs
    - Themes
    - Private
* Extra features
    - Sphinx
    - SMS
    - Translation
    - Queues and daemons
* After installation
    - Backups
    - Upgrading
* Additional configuration

Prerequisites
=============

PHP modules
-----------

The following software packages are *required* for this software to
run correctly.

- PHP 7.4+
- MariaDB 10.3+
- Web server    Apache, lighttpd and nginx will all work. CGI mode is
                recommended and also some variant of 'suexec' (or a
                proper setup php-fpm pool)
                NOTE: mod_rewrite or its equivalent is extremely useful.

Your PHP installation must include the following PHP extensions for a
functional setup of GNU social:

- openssl       (compiled in for Debian, enabled manually in Arch Linux)
- php-curl      Fetching files by HTTP.
- php-exif      Exchangeable image information.
- php-gd        Image manipulation (scaling).
- php-intl      Internationalization support (transliteration et al).
- php-json      For WebFinger lookups and more.
- php-mbstring  String manipulation
- php-mysql     The native driver for MariaDB connections.
- php-gmp       For Salmon signatures (part of OStatus)
- php-bcmath    Arbitrary Precision Mathematics
- php-opcache   Improved PHP performance by precompilation
- php-readline  For interactive scripts
- php-xml       XML parser

NOTE: Some distros require manual enabling in the relevant php.ini for some modules.

Better performance
------------------

For some functionality, you will also need the following extensions:

- opcache       Improves performance a _lot_. Included in PHP, must be
                enabled manually in php.ini for most distributions. Find
                and set at least:  opcache.enable=1
- mailparse     Efficient parsing of email requires this extension.
                Submission by email or SMS-over-email uses this.
- sphinx        A client for the sphinx server, an alternative to MySQL
                or Postgresql fulltext search. You will also need a
                Sphinx server to serve the search queries.
- gettext       For multiple languages. Default on many PHP installs;
                will be emulated if not present.
- exif          For thumbnails to be properly oriented.

You may also experience better performance from your site if you configure
a PHP cache/accelerator. Most distributions come with "opcache" support.
Enable it in your php.ini where it is documented together with its settings.

Installation
============

Getting it up and running
-------------------------

Installing the basic GNU Social web component is relatively easy,
especially if you've previously installed PHP/MariaDB packages.

1. Unpack the tarball you downloaded on your Web server. Usually a
   command like this will work:

       tar zxf gnusocial-*.tar.gz

   ...which will make a gnusocial-x.y.z subdirectory in your current
   directory. (If you don't have shell access on your Web server, you
   may have to unpack the tarball on your local computer and FTP the
   files to the server.)

2. Move the tarball to a directory of your choosing in your Web root
   directory. Usually something like this will work:

       mv gnusocial-x.y.z /var/www/gnusocial

   This will often make your GNU Social instance available in the gnusocial
   path of your server, like "http://example.net/gnusocial". "social" or
   "blog" might also be good path names. If you know how to configure
   virtual hosts on your web server, you can try setting up
   "http://social.example.net/" or the like.

   If you have "rewrite" support on your webserver, and you should,
   then please enable this in order to make full use of your site. This
   will enable "Fancy URL" support, which you can read more about if you
   scroll down a bit in this document.

3. Make your target directory writeable by the Web server, please note
   however that 'a+w' will give _all_ users write access and securing the
   webserver is not within the scope of this document.

       chmod a+w /var/www/gnusocial/

   On some systems, this will work as a more secure alternative:

       chgrp www-data /var/www/gnusocial/
       chmod g+w /var/www/gnusocial/

   If your Web server runs as another user besides "www-data", try
   that user's default group instead. As a last resort, you can create
   a new group like "gnusocial" and add the Web server's user to the group.

4. Create a database to hold your site data. Something like this
   should work (you will be prompted for your database password):

       mysqladmin -u "root" -p create social

   Note that GNU Social should have its own database; you should not share
   the database with another program. You can name it whatever you want,
   though.

   (If you don't have shell access to your server, you may need to use
   a tool like phpMyAdmin to create a database. Check your hosting
   service's documentation for how to create a new MariaDB database.)

5. Create a new database account that GNU Social will use to access the
   database. If you have shell access, this will probably work from the
   MariaDB shell:

       GRANT ALL on social.*
       TO 'social'@'localhost'
       IDENTIFIED BY 'agoodpassword';

   You should change the user identifier 'social' and 'agoodpassword'
   to your preferred new database username and password. You may want to
   test logging in to MariaDB as this new user.

6. In a browser, navigate to the GNU Social install script; something like:

       https://social.example.net/install.php

   Enter the database connection information and your site name. The
   install program will configure your site and install the initial,
   almost-empty database.

7. You should now be able to navigate to your social site's main directory
   and see the "Public Timeline", which will probably be empty. You can
   now register new user, post some notices, edit your profile, etc.

Fancy URLs
----------

By default, GNU Social will use URLs that include the main PHP program's
name in them. For example, a user's home profile might be found at either
of these URLS depending on the webserver's configuration and capabilities:

    https://social.example.net/index.php/fred
    https://social.example.net/index.php?p=fred

It's possible to configure the software to use fancy URLs so it looks like
this instead:

    https://social.example.net/fred

These "fancy URLs" are more readable and memorable for users. To use
fancy URLs, you must either have Apache 2.x with .htaccess enabled and
mod_rewrite enabled, -OR- know how to configure "url redirection" in
your server (like lighttpd or nginx).

1. See the instructions for each respective webserver software:
    * For Apache, inspect the "htaccess.sample" file and save it as
        ".htaccess" after making any necessary modifications. Our sample
        file is well commented.
    * For lighttpd, inspect the lighttpd.conf.example file and apply the
        appropriate changes in your virtualhost configuration for lighttpd.
    * For nginx, inspect the nginx.conf.sample file and apply the appropriate
        changes.
    * For other webservers, we gladly accept contributions of
        server configuration examples.

2. Assuming your webserver is properly configured and have its settings
    applied (remember to reload/restart it), you can add this to your
    GNU social's config.php file:
       $config['site']['fancy'] = true;

You should now be able to navigate to a "fancy" URL on your server,
like:

    https://social.example.net/main/register

Themes
------

As of right now, your ability change the theme is limited to CSS
stylesheets and some image files; you can't change the HTML output,
like adding or removing menu items, without the help of a plugin.

You can choose a theme using the $config['site']['theme'] element in
the config.php file. See below for details.

You can add your own theme by making a sub-directory of the 'theme'
subdirectory with the name of your theme. Each theme can have the
following files:

display.css: a CSS2 file for "default" styling for all browsers.
logo.png: a logo image for the site.
default-avatar-profile.png: a 96x96 pixel image to use as the avatar for
users who don't upload their own.
default-avatar-stream.png: Ditto, but 48x48. For streams of notices.
default-avatar-mini.png: Ditto ditto, but 24x24. For subscriptions
listing on profile pages.

You may want to start by copying the files from the default theme to
your own directory.

Private
-------

A GNU social node can be configured as "private", which means it will not
federate with other nodes in the network. It is not a recommended method
of using GNU social and we cannot at the current state of development
guarantee that there are no leaks (what a public network sees as features,
private sites will likely see as bugs).

Private nodes are however an easy way to easily setup collaboration and
image sharing within a workgroup or a smaller community where federation
is not a desired feature. Also, it is possible to change this setting and
instantly gain full federation features.

Access to file attachments can also be restricted to logged-in users only:

1. Add a directory outside the web root where your file uploads will be
   stored. Use this command as an initial guideline to create it:

       mkdir /var/www/gnusocial-files

2. Make the file uploads directory writeable by the web server. An
   insecure way to do this is (to do it properly, read up on UNIX file
   permissions and configure your webserver accordingly):

       chmod a+x /var/www/gnusocial-files

3. Tell GNU social to use this directory for file uploads. Add a line
   like this to your config.php:

       $config['attachments']['dir'] = '/var/www/gnusocial-files';

Extra features
==============

Sphinx
------

To use a Sphinx server to search users and notices, you'll need to
enable the SphinxSearch plugin. Add to your config.php:

    addPlugin('SphinxSearch');
    $config['sphinx']['server'] = 'searchhost.local';

You also need to install, compile and enable the sphinx pecl extension for
php on the client side, which itself depends on the sphinx development files.

See plugins/SphinxSearch/README for more details and server setup.

SMS
---

StatusNet supports a cheap-and-dirty system for sending update messages
to mobile phones and for receiving updates from the mobile. Instead of
sending through the SMS network itself, which is costly and requires
buy-in from the wireless carriers, it simply piggybacks on the email
gateways that many carriers provide to their customers. So, SMS
configuration is essentially email configuration.

Each user sends to a made-up email address, which they keep a secret.
Incoming email that is "From" the user's SMS email address, and "To"
the users' secret email address on the site's domain, will be
converted to a notice and stored in the DB.

For this to work, there *must* be a domain or sub-domain for which all
(or most) incoming email can pass through the incoming mail filter.

1. Run the SQL script carrier.sql in your StatusNet database. This will
   usually work:

       mysql -u "statusnetuser" --password="statusnetpassword" statusnet < db/carrier.sql

   This will populate your database with a list of wireless carriers
   that support email SMS gateways.

2. Make sure the maildaemon.php file is executable:

       chmod +x scripts/maildaemon.php

   Note that "daemon" is kind of a misnomer here; the script is more
   of a filter than a daemon.

2. Edit /etc/aliases on your mail server and add the following line:

       *: /path/to/statusnet/scripts/maildaemon.php

3. Run whatever code you need to to update your aliases database. For
   many mail servers (Postfix, Exim, Sendmail), this should work:

       newaliases

   You may need to restart your mail server for the new database to
   take effect.

4. Set the following in your config.php file:

       $config['mail']['domain'] = 'yourdomain.example.net';

Translations
------------

For info on helping with translations, see the platform currently in use
for translations: https://www.transifex.com/projects/p/gnu-social/

Translations use the gettext system <http://www.gnu.org/software/gettext/>.
If you for some reason do not wish to sign up to the Transifex service,
you can review the files in the "locale/" sub-directory of GNU social.
Each plugin also has its own translation files.

To get your own site to use all the translated languages, and you are
tracking the git repo, you will need to install at least 'gettext' on
your system and then run:
    $ make translations

Queues and daemons
------------------

Some activities that StatusNet needs to do, like broadcast OStatus, SMS,
XMPP messages and TwitterBridge operations, can be 'queued' and done by
off-line bots instead.

Two mechanisms are available to achieve offline operations:

* New embedded OpportunisticQM plugin, which is enabled by default
* Legacy queuedaemon script, which can be enabled via config file.

### OpportunisticQM plugin

This plugin is enabled by default. It tries its best to do background
jobs during regular HTTP requests, like API or HTML pages calls.

Since queueing system is enabled by default, notices to be broadcasted
will be stored, by default, into DB (table queue_item).

Whenever it has time, OpportunisticQM will try to handle some of them.

This is a good solution whether you:

* have no access to command line (shared hosting)
* do not want to deal with long-running PHP processes
* run a low traffic GNU social instance

In other case, you really should consider enabling the queuedaemon for
performance reasons. Background daemons are necessary anyway if you wish
to use the Instant Messaging features such as communicating via XMPP.

### queuedaemon

If you want to use legacy queuedaemon, you must be able to run
long-running offline processes, either on your main Web server or on
another server you control. (Your other server will still need all the
above prerequisites, with the exception of Apache.) Installing on a
separate server is probably a good idea for high-volume sites.

1. You'll need the "CLI" (command-line interface) version of PHP
   installed on whatever server you use.

   Modern PHP versions in some operating systems have disabled functions
   related to forking, which is required for daemons to operate. To make
   this work, make sure that your php-cli config (/etc/php5/cli/php.ini)
   does NOT have these functions listed under 'disable_functions':

       * pcntl_fork, pcntl_wait, pcntl_wifexited, pcntl_wexitstatus,
         pcntl_wifsignaled, pcntl_wtermsig

   Other recommended settings for optimal performance are:
       * mysqli.allow_persistent = On
       * mysqli.reconnect = On

2. If you're using a separate server for queues, install StatusNet
   somewhere on the server. You don't need to worry about the
   .htaccess file, but make sure that your config.php file is close
   to, or identical to, your Web server's version.

3. In your config.php files (on the server where you run the queue
    daemon), set the following variable:

       $config['queue']['daemon'] = true;

   You may also want to look at the 'Queues and Daemons' section in
   this file for more background processing options.

4. On the queues server, run the command scripts/startdaemons.sh.

This will run the queue handlers:

* queuedaemon.php - polls for queued items for inbox processing and
  pushing out to OStatus, SMS, XMPP, etc.
* imdaemon.php - if an IM plugin is enabled (like XMPP)
* other daemons, like TwitterBridge ones, that you may have enabled

These daemons will automatically restart in most cases of failure
including memory leaks (if a memory_limit is set), but may still die
or behave oddly if they lose connections to the XMPP or queue servers.

It may be a good idea to use a daemon-monitoring service, like 'monit',
to check their status and keep them running.

All the daemons write their process IDs (pids) to /var/run/ by
default. This can be useful for starting, stopping, and monitoring the
daemons. If you are running multiple sites on the same machine, it will
be necessary to avoid collisions of these PID files by setting a site-
specific directory in config.php:

       $config['daemon']['piddir'] = __DIR__ . '/../run/';

It is also possible to use a STOMP server instead of our kind of hacky
home-grown DB-based queue solution. This is strongly recommended for
best response time, especially when using XMPP.

After installation
==================

Backups
-------

There is no built-in system for doing backups in GNU social. You can make
backups of a working StatusNet system by backing up the database and
the Web directory. To backup the database use mysqldump <https://mariadb.com/kb/en/mariadb/mysqldump/>
and to backup the Web directory, try tar.

Upgrading
---------

Upgrading is strongly recommended to stay up to date with security fixes
and new features. For instructions on how to upgrade GNU social code,
please see the UPGRADE file.

Additional configuration
------------------------

Please refer to DOCUMENTATION/SYSTEM_ADMINISTRATORS/CONFIGURE for information.
