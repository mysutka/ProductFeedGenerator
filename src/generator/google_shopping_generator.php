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
			"g:availability" => "STOCKCOUNT",

			"g:price" => "BASEPRICE_VAT",
			"g:sale_price" => "PRICE_VAT",

			"SALE_PRICE_START_DATE" => "SALE_PRICE_START_DATE",
			"SALE_PRICE_END_DATE" => "SALE_PRICE_END_DATE",
		];
	}

	function afterFilter($values) {
		if ($values["g:price"] == $values["g:sale_price"]) {
			unset($values["g:sale_price"]);
		}
		$values["g:availability"] = ($values["g:availability"] > 0) ? "in stock" : "out of stock";
		$ids = [
			(isset($values["g:gtin"]) ? $values["g:gtin"] : null),
			$mpn = null,
			(isset($values["g:brand"]) ? $values["g:brand"] : null),
		];
		if (count(array_filter($ids))<2) {
			$values["g:identifier_exists"] = "no";
		}
		$sale_price_dates = [
			isset($values["SALE_PRICE_START_DATE"]) ? date("c", strtotime($values["SALE_PRICE_START_DATE"])) : null,
			isset($values["SALE_PRICE_END_DATE"]) ? date("c", strtotime($values["SALE_PRICE_END_DATE"])) : null,
		];
		# potrebujeme mit oba datumy, abychom platnost dali do feedu
		if (sizeof(array_filter($sale_price_dates)) === 2) {
			$values["g:sale_price_effective_date"] = join("/", $sale_price_dates);
		}
		unset($values["SALE_PRICE_START_DATE"]);
		unset($values["SALE_PRICE_END_DATE"]);
		return $values;
	}
}
