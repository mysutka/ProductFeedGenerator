<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;
use ProductFeedGenerator\Reader\Atk14EshopReader;

class GoogleShoppingGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"eshop_url" => ATK14_HTTP_HOST,
			"price_with_currency" => true,
			"category_path_connector" => ">",
			"xml_item_element_name" => "entry",
			"feed_title" => sprintf("%s | %s", ATK14_APPLICATION_DESCRIPTION, ATK14_APPLICATION_NAME),

			"feed_begin" => '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">',
			"feed_end" => '</feed>',
			"fixed_values" => [
				"g:condition" => "new",
				"g:availability" => "in stock",
			],
		];
		return parent::__construct($reader, $options);
	}

	function getAttributesMap() {
		return [
			"g:product_type" => "CATEGORYTEXT",
			"description" => "DESCRIPTION",
			"g:gtin" => "EAN",
			"g:image_link" => "IMGURL",
			"g:id" => "ITEM_ID",
			"g:item_group_id" => "ITEMGROUP_ID",
			"title" => "PRODUCTNAME",
			"link" => "URL",
			"g:brand" => "MANUFACTURER",

			"g:price" => "BASEPRICE_VAT",
			"g:sale_price" => "PRICE_VAT",
			"g:sale_price_effective_date" => "SALEPRICE_EFFECTIVE_DATE",
		];
	}

	function afterFilter($values) {
		if ($values["g:price"] == $values["g:sale_price"]) {
			unset($values["g:sale_price"]);
		}
		$ids = [
			(isset($values["g:gtin"]) ? $values["g:gtin"] : null),
			$mpn = null,
			(isset($values["g:brand"]) ? $values["g:brand"] : null),
		];
		if (count(array_filter($ids))<2) {
			$values["g:identifier_exists"] = "no";
		}
		return $values;
	}
}
