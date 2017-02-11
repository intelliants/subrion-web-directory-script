<?php
//##copyright##

interface iaDirectoryPackage
{
	const COLUMN_ID = 'id';

	const STATUS_BANNED = 'banned';
	const STATUS_SUSPENDED = 'suspended';
}

abstract class abstractDirectoryPackageAdmin extends abstractModuleAdmin implements iaDirectoryPackage
{
	protected $_moduleName = 'directory';
}

abstract class abstractDirectoryPackageFront extends abstractModuleFront implements iaDirectoryPackage
{
	protected $_moduleName = 'directory';
}