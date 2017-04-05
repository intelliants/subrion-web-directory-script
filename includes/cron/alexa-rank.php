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

$iaListing = $iaCore->factoryModule('listing', 'directory');

if ($listings = $iaDb->all(['id', 'domain', 'alexa_rank'], "`domain` != ''", 0, null, iaListing::getTable())) {
    include_once IA_MODULES . 'directory/includes/alexarank.inc.php';

    $iaAlexaRank = new iaAlexaRank();

    foreach ($listings as $row) {
        if ($alexaData = $iaAlexaRank->getAlexa($row['domain'])) {
            if ($alexaData['rank'] != $row['alexa_rank']) {
                $iaCore->iaDb->update(['alexa_rank' => $alexaData['rank'], 'id' => $row['id']], null, null, iaListing::getTable());
            }
        }
    }
}
