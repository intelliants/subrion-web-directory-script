<?php
/******************************************************************************
 *
 * Subrion Web Directory Script
 * Copyright (C) 2017 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion Web Directory Script.
 *
 * This program is a commercial software and any kind of using it must agree
 * to the license, see <https://subrion.pro/license.html>.
 *
 * This copyright notice may not be removed from the software source without
 * the permission of Subrion respective owners.
 *
 *
 * @link https://subrion.pro/product/directory.html
 *
 ******************************************************************************/

interface iaDirectoryModule
{
    const COLUMN_ID = 'id';

    const STATUS_BANNED = 'banned';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_TRASH = 'trash';
}

abstract class abstractDirectoryModuleAdmin extends abstractModuleAdmin implements iaDirectoryModule
{
    protected $_moduleName = 'directory';
}

abstract class abstractDirectoryDirectoryFront extends abstractModuleFront implements iaDirectoryModule
{
    protected $_moduleName = 'directory';
}
