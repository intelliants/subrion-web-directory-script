<?php
//##copyright##

interface iaDirectoryPackage
{
	const COLUMN_ID = 'id';

	const STATUS_BANNED = 'banned';
	const STATUS_SUSPENDED = 'suspended';
}

abstract class abstractDirectoryPackageAdmin extends abstractPackageAdmin implements iaDirectoryPackage
{
	protected $_packageName = 'directory';
}

abstract class abstractDirectoryPackageFront extends abstractPackageFront implements iaDirectoryPackage
{
	protected $_packageName = 'directory';
}