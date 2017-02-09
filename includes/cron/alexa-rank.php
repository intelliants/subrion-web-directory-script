<?php
//##copyright##

$iaListing = $iaCore->factoryPackage('listing', 'directory');

if ($listings = $iaDb->all(['id', 'domain', 'alexa_rank'], "`domain` != ''", 0, null, iaListing::getTable()))
{
	include_once IA_PACKAGES . 'directory' . IA_DS . 'includes' . IA_DS . 'alexarank.inc.php';

	$iaAlexaRank = new iaAlexaRank();

	foreach ($listings as $row)
	{
		if ($alexaData = $iaAlexaRank->getAlexa($row['domain']))
		{
			if ($alexaData['rank'] != $row['alexa_rank'])
			{
				$iaCore->iaDb->update(['alexa_rank' => $alexaData['rank'], 'id' => $row['id']], null, null, iaListing::getTable());
			}
		}
	}
}