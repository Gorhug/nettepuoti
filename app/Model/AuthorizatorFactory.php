<?php
namespace App\Model;

class AuthorizatorFactory
{
	public static function create(): \Nette\Security\Permission {
		$acl = new \Nette\Security\Permission;
		$acl->addRole('guest');
        $acl->addRole('authenticated', 'guest'); // 'registered' inherits from 'guest'
        $acl->addRole('admin', 'authenticated'); // and 'admin' inherits from 'registered'
		$acl->addResource('product');
		$acl->addResource('user');
        $acl->allow('admin');
		return $acl;
	}
}

