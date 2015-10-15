<?php
//##copyright##

$iaListing = $iaCore->factoryPackage('listing', 'directory', iaCore::ADMIN);

$values = array(
	'sponsored' => 0,
	'sponsored_end' => null,
	'sponsored_plan_id' => 0,
	'status' => iaListing::STATUS_SUSPENDED
);
$stmt = '`sponsored` != 0 AND `sponsored_end` < CURRENT_TIMESTAMP';
$iaCore->iaDb->update($values, $stmt, null, iaListing::getTable());

$values = array(
	'featured' => 0,
	'featured_end' => null
);
$stmt = '`featured` != 0 AND `featured_end` < CURRENT_TIMESTAMP';
$iaCore->iaDb->update($values, $stmt, null, iaListing::getTable());