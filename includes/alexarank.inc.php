<?php
//##copyright##

include_once IA_INCLUDES . 'utils' . IA_DS . 'simplexml.class.php';

class iaAlexaRank extends simplexml
{
	public function getAlexa($url)
	{
		$data = $this->_fetchData($url);
		if (is_array($data))
		{
			return $this->_findValue($data) ;
		}

		return null;
	}

	private function _fetchData($listingUrl)
	{
		$url = "http://data.alexa.com/data?cli=10&dat=snbamz&url=http://%s";
		$url = sprintf($url, $listingUrl);
		$response = file_get_contents($url, false);
		$data = $this->xml_load_string($response, 'array');

		return $data;
	}

	private function _findValue($data)
	{
		$values = array(
			'rank' => (isset($data['SD'][1]['POPULARITY']['@attributes']['TEXT']) ? ($data['SD'][1]['POPULARITY']['@attributes']['TEXT']) : null),
			'created' => (isset($data['SD'][0]['CREATED']['@attributes']['DATE']) ? ($data['SD'][0]['CREATED']['@attributes']['DATE']) : null),
			'email' => (isset($data['SD'][0]['EMAIL']['@attributes']['ADDR']) ? ($data['SD'][0]['EMAIL']['@attributes']['ADDR']) : null),
			'linksin' => (isset($data['SD'][0]['LINKSIN']['@attributes']['NUM']) ? ($data['SD'][0]['LINKSIN']['@attributes']['NUM']) : null),
			'reach' => (isset($data['SD'][1]['REACH']['@attributes']['RANK']) ? ($data['SD'][1]['REACH']['@attributes']['RANK']) : null),
			'baseuri' => (isset($data['DMOZ']['SITE']['@attributes']['BASE']) ? ($data['DMOZ']['SITE']['@attributes']['BASE']) : null),
			'title' => (isset($data['DMOZ']['SITE']['@attributes']['TITLE']) ? ($data['DMOZ']['SITE']['@attributes']['TITLE']) : null),
			'description' => (isset($data['DMOZ']['SITE']['@attributes']['DESC']) ? ($data['DMOZ']['SITE']['@attributes']['DESC']) : null),
		);

		return $values;
	}
}