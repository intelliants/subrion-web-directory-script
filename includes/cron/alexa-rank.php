<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2016 Intelliants, LLC <http://www.intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link http://www.subrion.org/
 *
 ******************************************************************************/

$iaListing = $iaCore->factoryPackage('listing', 'directory');

$listings = $iaDb->all(array('id', 'domain'), null, 0, null, iaListing::getTable());

if ($listings)
{
	include_once IA_PACKAGES . 'directory' . IA_DS . 'includes' . IA_DS . 'alexarank.inc.php';

	$iaAlexaRank = new iaAlexaRank();

	foreach ($listings as $row)
	{
		if ($alexaData = $iaAlexaRank->getAlexa($row['domain']))
		{
			$iaListing->update(array('alexa_rank' => $alexaData['rank'], 'id' => $row['id']));
		}
	}
}