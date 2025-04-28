<?php
/*
Copyright Ilkka Forsblom.

This file is part of Nettepuoti.

Nettepuoti is free software: you can redistribute it and/or modify 
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the 
License, or (at your option) any later version.

Nettepuoti is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License 
along with Nettepuoti. If not, see <https://www.gnu.org/licenses/>. 
*/
namespace App\Model;

class AuthorizatorFactory
{
	public static function create(): \Nette\Security\Permission {
		$acl = new \Nette\Security\Permission;
		$acl->addRole('guest');
        $acl->addRole('authenticated', 'guest'); // 'registered' inherits from 'guest'
        $acl->addRole('admin', 'authenticated'); // and 'admin' inherits from 'registered'
		$acl->addResource('product');
		$acl->addResource('category');
		$acl->addResource('media');
		$acl->addResource('user');
		$acl->addResource('activitypub');
        $acl->allow('admin');
		return $acl;
	}
}

