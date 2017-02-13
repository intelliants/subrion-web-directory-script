<?php
//##copyright##

interface iaDirectoryModule
{
	const COLUMN_ID = 'id';

	const STATUS_BANNED = 'banned';
	const STATUS_SUSPENDED = 'suspended';
}

abstract class abstractDirectoryModuleAdmin extends abstractModuleAdmin implements iaDirectoryModule
{
	protected $_moduleName = 'directory';
}

abstract class abstractDirectoryDirectoryFront extends abstractModuleFront implements iaDirectoryModule
{
	protected $_moduleName = 'directory';
}