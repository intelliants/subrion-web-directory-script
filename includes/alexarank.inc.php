<?php
/******************************************************************************
 *
 * Subrion Web Directory Script
 * Copyright (C) 2018 Intelliants, LLC <https://intelliants.com>
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

/**
 * PHP Class to get a website Alexa Ranking
 * @author http://www.paulund.co.uk
 *
 */
class iaAlexaRank
{
    public function getAlexa($domain)
    {
        $data = iaUtil::getPageContent("http://data.alexa.com/data?cli=10&dat=snbamz&url=http://" . $domain);

        $xml = new SimpleXMLElement($data);

        //Get popularity node
        $popularity = $xml->xpath("//POPULARITY");
        $reviews = $xml->xpath('//REVIEWS');
        $speed = $xml->xpath('//SPEED');
        $links = $xml->xpath('//LINKSIN');
        $category = $xml->xpath('//CATS/CAT');
        $name = $xml->xpath('//DMOZ/SITE');

        return [
            'name' => (string)$name[0]['TITLE'],
            'category' => (string)$category[0]['TITLE'],
            'rank' => (int)$popularity[0]['TEXT'],
            'links' => number_format((int)$links[0]['NUM'], 0),
            'reviews_stars' => (string)$reviews[0]['AVG'],
            'reviews_num' => (string)$reviews[0]['NUM'],
            'speed_time' => (int)$speed[0]['TEXT'] / 1000,
            'speed_percent' => (100 - (int)$speed[0]['PCT']) . '% of sites are faster.'
        ];
    }
}
