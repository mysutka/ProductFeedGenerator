<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

class ZboziCzGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"category_path_connector" => "|",
			"xml_item_element_name" => "SHOPITEM",
			"feed_begin" => '<SHOP xmlns="http://www.zbozi.cz/ns/offer/1.0">',
			"feed_end" => "</SHOP>",
			"fixed_values" => [
				"DELIVERY_DATE" => 0,
			],
		];
		return parent::__construct($reader, $options);
	}

	function getAttributesMap() {
		return [
			"CATEGORYTEXT" => "CATEGORYTEXT",
			"DESCRIPTION" => "DESCRIPTION",
			"EAN" => "EAN",
			"IMGURL" => "IMGURL",
			"ITEM_ID" => "ITEM_ID",
			"PRODUCT" => "PRODUCT",
			"PRODUCTNAME" => "PRODUCTNAME",
			"URL" => "URL",
			"MANUFACTURER" => "MANUFACTURER",

			"PRICE_VAT" => "PRICE_VAT",
			"LIST_PRICE" => "BASEPRICE_VAT",
		];
	}
}
