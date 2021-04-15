<?php

// {{{ License
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
// }}}

namespace App\Tests\Util;

use App\Util\Common;
use App\Util\Exception\NicknameEmptyException;
use App\Util\Exception\NicknameInvalidException;
use App\Util\Exception\NicknameReservedException;
use App\Util\Exception\NicknameTooShortException;
use App\Util\Nickname;
use Jchook\AssertThrows\AssertThrows;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class NicknameTest extends WebTestCase
{
    use AssertThrows;

    public function testNormalize()
    {
        $conf = ['nickname' => ['min_length' => 4, 'reserved' => ['this_nickname_is_reserved']]];
        $cb   = $this->createMock(ContainerBagInterface::class);
        static::assertTrue($cb instanceof ContainerBagInterface);
        $cb->method('get')
           ->willReturnMap([['gnusocial', $conf], ['gnusocial_defaults', $conf]]);
        Common::setupConfig($cb);

        static::assertSame('foobar', Nickname::normalize('foobar', check_already_used: false));
        static::assertSame('foobar', Nickname::normalize('  foobar  ', check_already_used: false));
        static::assertSame('foobar', Nickname::normalize('foo_bar', check_already_used: false));
        static::assertSame('foobar', Nickname::normalize('FooBar', check_already_used: false));
        static::assertThrows(NicknameTooShortException::class, function () { return Nickname::normalize('foo', check_already_used: false); });
        static::assertThrows(NicknameEmptyException::class, function () { return Nickname::normalize('', check_already_used: false); });
        static::assertThrows(NicknameInvalidException::class, function () { return Nickname::normalize('FóóBár', check_already_used: false); });
        static::assertThrows(NicknameReservedException::class, function () { return Nickname::normalize('this_nickname_is_reserved', check_already_used: false); });
    }

    public function testIsCanonical()
    {
        static::assertTrue(Nickname::isCanonical('foo'));
        static::assertFalse(Nickname::isCanonical('fóó'));
    }

    public function testIsReserved()
    {
        $conf = ['nickname' => ['min_length' => 4, 'reserved' => ['this_nickname_is_reserved']]];
        $cb   = $this->createMock(ContainerBagInterface::class);
        static::assertTrue($cb instanceof ContainerBagInterface);
        $cb->method('get')
           ->willReturnMap([['gnusocial', $conf], ['gnusocial_defaults', $conf]]);
        Common::setupConfig($cb);

        static::assertTrue(Nickname::isReserved('this_nickname_is_reserved'));
        static::assertFalse(Nickname::isReserved('this_nickname_is_not_reserved'));
    }
}