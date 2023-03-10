<?php
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Bootstrapping code to initialize the application.
 *
 * @package   GNUsocial
 * @author    Evan Prodromou
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @author    Neil E. Hodges <47hasbegun@gmail.com>
 * @author    Brion Vibber <brion@pobox.com>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2010-2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

define('GNUSOCIAL_ENGINE', 'GNU social');
define('GNUSOCIAL_ENGINE_URL', 'https://gnusocial.rocks/');
define('GNUSOCIAL_ENGINE_REPO_URL', 'https://notabug.org/diogo/gnu-social/'); // Change to https://git.gnu.io/gnu/gnu-social

define('GNUSOCIAL_BASE_VERSION', '2.0.1');
define('GNUSOCIAL_LIFECYCLE', 'beta0'); // 'dev', 'alpha[0-9]+', 'beta[0-9]+', 'rc[0-9]+', 'release'

define('GNUSOCIAL_VERSION', GNUSOCIAL_BASE_VERSION . '-' . GNUSOCIAL_LIFECYCLE);

define('GNUSOCIAL_CODENAME', 'THIS. IS. GNU social!!!!');

define('AVATAR_PROFILE_SIZE', 96);
define('AVATAR_STREAM_SIZE', 48);
define('AVATAR_MINI_SIZE', 24);

define('NOTICES_PER_PAGE', 20);
define('PROFILES_PER_PAGE', 20);
define('MESSAGES_PER_PAGE', 20);
define('GROUPS_PER_PAGE', 20);
define('APPS_PER_PAGE', 20);
define('PEOPLETAGS_PER_PAGE', 20);

define('GROUPS_PER_MINILIST', 8);
define('PROFILES_PER_MINILIST', 8);

define('FOREIGN_NOTICE_SEND', 1);
define('FOREIGN_NOTICE_RECV', 2);
define('FOREIGN_NOTICE_SEND_REPLY', 4);
define('FOREIGN_NOTICE_SEND_REPEAT', 8);

define('FOREIGN_FRIEND_SEND', 1);
define('FOREIGN_FRIEND_RECV', 2);

define('NOTICE_INBOX_SOURCE_SUB', 1);
define('NOTICE_INBOX_SOURCE_GROUP', 2);
define('NOTICE_INBOX_SOURCE_REPLY', 3);
define('NOTICE_INBOX_SOURCE_FORWARD', 4);
define('NOTICE_INBOX_SOURCE_PROFILE_TAG', 5);
define('NOTICE_INBOX_SOURCE_GATEWAY', -1);

/**
 * StatusNet had this string as valid path characters: '\pN\pL\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\'\@'
 * Some of those characters can be troublesome when auto-linking plain text. Such as "http://some.com/)"
 * URL encoding should be used whenever a weird character is used, the following strings are not definitive.
 */
define('URL_REGEX_VALID_PATH_CHARS', '\pN\pL\,\!\.\:\-\_\+\/\@\=\;\%\~\*\(\)');
define('URL_REGEX_VALID_QSTRING_CHARS', URL_REGEX_VALID_PATH_CHARS . '\&');
define('URL_REGEX_VALID_FRAGMENT_CHARS', URL_REGEX_VALID_QSTRING_CHARS . '\?\#');
define('URL_REGEX_EXCLUDED_END_CHARS', '\?\.\,\!\#\:\'');  // don't include these if they are directly after a URL
define('URL_REGEX_DOMAIN_NAME', '(?:(?!-)[A-Za-z0-9\-]{1,63}(?<!-)\.)+[A-Za-z]{2,10}');

// Autoload composer dependencies
require_once INSTALLDIR . '/vendor/autoload.php';

// append our extlib dir as the last-resort place to find libs
set_include_path(get_include_path() . PATH_SEPARATOR . INSTALLDIR . '/extlib/');

// global configuration object

// This is awful but system's PEAR always gives us issues, we've patched it
require_once INSTALLDIR . '/extlib/' . 'PEAR.php';
require_once INSTALLDIR . '/extlib/' . 'PEAR/Exception.php';
global $_PEAR;
$_PEAR = new PEAR;
$_PEAR->setErrorHandling(PEAR_ERROR_CALLBACK, 'PEAR_ErrorToPEAR_Exception');

require_once INSTALLDIR . '/extlib/' . 'MDB2.php';
require_once INSTALLDIR . '/extlib/' . 'DB/DataObject.php';
require_once INSTALLDIR . '/extlib/' . 'DB/DataObject/Cast.php';  // for dates

require_once INSTALLDIR . '/lib/util/language.php';

// This gets included before the config file, so that admin code and plugins
// can use it

require_once INSTALLDIR . '/lib/util/event.php';
require_once INSTALLDIR . '/lib/modules/Module.php';
require_once INSTALLDIR . '/lib/modules/Plugin.php';

function addPlugin($name, array $attrs = [])
{
    return GNUsocial::addPlugin($name, $attrs);
}

function _have_config()
{
    return GNUsocial::haveConfig();
}

function GNUsocial_class_autoload($cls)
{
    if (mb_substr($cls, -6) == 'Action' &&
        file_exists(($file = INSTALLDIR . '/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php'))) {
        require_once $file;
    }

    $lib_path = INSTALLDIR . '/lib/';
    $lib_dirs = array_map(function ($dir) {
        return '/lib/' . $dir . '/';
    }, array_filter(
        scandir($lib_path),
        function ($dir) use ($lib_path) {
            // Filter out files and both hidden and implicit directories.
            return $dir[0] !== '.' && is_dir($lib_path . $dir);
        }
    ));

    $found = false;
    foreach (array_merge(['/classes/'], $lib_dirs) as $dir) {
        $file = (in_array($dir, ['/classes/', '/lib/modules/'])) ? $cls : strtolower($cls);
        $inc = INSTALLDIR . $dir . $file . '.php';
        if (file_exists($inc)) {
            $found = (require_once $inc);
            break;
        }
    }

    if (!$found) {
        Event::handle('Autoload', [&$cls]);
    }
}

// Autoload function queue, starting with our own discovery method
spl_autoload_register('GNUsocial_class_autoload');

/**
 * Extlibs with namespaces (or directly in extlib/)
 * This covers libraries such as: Validate and \Michelf\Markdown
 *
 * The namespaced based structure is called "PSR-0 autoloading standard":
 *    \<Vendor Name>\(<Namespace>\)*<Class Name>
 * and is available here: http://www.php-fig.org/psr/psr-0/
 */
spl_autoload_register(function ($class) {
    if ($class === 'OAuthRequest' || $class === 'OAuthException') {
        $class_base = 'OAuth';
    } else {
        $class_base = preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\'));
    }
    $file = INSTALLDIR . "/extlib/{$class_base}.php";
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    # Try if the system has this external library
    $file = "/usr/share/php/{$class_base}.php";
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});
require_once INSTALLDIR . '/lib/util/util.php';
require_once INSTALLDIR . '/lib/action/action.php';
require_once INSTALLDIR . '/lib/util/mail.php';

//set PEAR error handling to use regular PHP exceptions
function PEAR_ErrorToPEAR_Exception(PEAR_Error $err)
{
    //DB_DataObject throws error when an empty set would be returned
    //That behavior is weird, and not how the rest of StatusNet works.
    //So just ignore those errors.
    if ($err->getCode() == DB_DATAOBJECT_ERROR_NODATA) {
        return;
    }

    $msg = $err->getMessage();
    $userInfo = $err->getUserInfo();

    // Log this; push the message up as an exception

    common_log(LOG_ERR, "PEAR Error: $msg ($userInfo)");

    // HACK: queue handlers get kicked by the long-query killer, and
    // keep the same broken connection. We die here to get a new
    // process started.

    if (php_sapi_name() == 'cli' && preg_match('/nativecode=2006/', $userInfo)) {
        common_log(LOG_ERR, "Lost DB connection; dying.");
        exit(100);
    }

    if ($err->getCode()) {
        throw new PEAR_Exception($err->getMessage(), $err->getCode());
    }
    throw new PEAR_Exception($err->getMessage());
}
