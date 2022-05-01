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
 * Implementation of WebFinger resource discovery (RFC7033)
 *
 * @category  Discovery
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class LRDDMethod_WebFinger extends LRDDMethod
{
    /**
     * Simply returns the WebFinger URL over HTTPS at the uri's domain:
     * https://{domain}/.well-known/webfinger?resource={uri}
     */
    public function discover($uri)
    {
        $scheme = mb_strtolower(parse_url($uri, PHP_URL_SCHEME));
        switch ($scheme) {
        case 'acct':
            // We can't use parse_url data for this, since the 'host'
            // entry is only set if the scheme has '://' after it.
            $parts = explode('@', parse_url($uri, PHP_URL_PATH), 2);

            if (!Discovery::isAcct($uri) || count($parts) != 2) {
                throw new Exception('Bad resource URI: ' . $uri);
            }
            [, $domain] = $parts;
            break;
        case 'http':
        case 'https':
            $domain = mb_strtolower(parse_url($uri, PHP_URL_HOST));
            break;
        default:
            throw new Exception('Unable to discover resource descriptor endpoint.');
        }

        $link = new XML_XRD_Element_Link(
            Discovery::LRDD_REL,
            'https://' . $domain . '/.well-known/webfinger?resource={uri}',
            Discovery::JRD_MIMETYPE,
            true // isTemplate
        );

        return [$link];
    }
}
