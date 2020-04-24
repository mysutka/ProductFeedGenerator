<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

class GoogleMerchantsGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"price_with_currency" => true,
			"category_path_connector" => ">",
			"output_format" => "csv",
#			"xml_item_element_name" => "entry",
		];
		return parent::__construct($reader, $options);
	}

	function getAttributesMap() {
		return [
			"ID" => "ITEM_ID",
			"Item description" => "DESCRIPTION",
			"Image URL" => "IMGURL",
			"Item category" => "CATEGORYTEXT",
			"Final URL" => "URL",
			"Item title" => "PRODUCTNAME",
			"Price" => "PRICE_VAT",
		];
	}
}
