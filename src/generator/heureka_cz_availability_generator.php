<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

use ProductFeedGenerator\Reader\Atk14EshopReader;
/**
 * Generator dostupnostniho feedu. Vyzaduje specifickou metodu, ktera vytvori xml elementy s atributama.
 *
 */
class HeurekaCzAvailabilityGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"xml_item_element_name" => "item",

			"feed_begin" => "<item_list>",
			"feed_end" => "</item_list>",
		];
		return parent::__construct($reader, $options);
	}

	function getAttributesMap(): array {
		return [
			"stock_quantity" => "STOCKCOUNT",
			"item_id" => "CATALOG_ID",
		];
	}

	/**
	 * Trida ProductFeedGenerator\FeedGenerator zatim neumi tvorit xml elementy s atributama.
	 * Cilem je dosahnout nodu ve tvaru
	 * ```
	 * <item id="37"><stock_quantity>20</stock_quantity></item>
	 * ```
	 *
	 * Tady si vypomuzeme tak, ze pouzijeme php tridu SimpleXmlElement.
	 * Vygenerujeme si node s elementem item_id
	 * a hodnotu item_id preneseme do atributu id v nodu item.
	 * Element item_id pak smazeme.
	 */
	function _array_to_xml($ar,$root_element = "",$_prev_root_element=""){
		if (!$ar) {return null;}
		$xml = parent::_array_to_xml($ar, $root_element, $_prev_root_element);

		# LIBXML_NOBLANKS - bez teto konstanty dojde k tomu, ze smazany element vytvori ve vystupu prazdny radek
		$_xml = new \SimpleXmlElement($xml, LIBXML_NOBLANKS);
		$item = $_xml->xpath("/item");

		if ($_item_id = (string)$item[0]->item_id) {
			$_xml->addAttribute("id", $_item_id);
			unset($item[0]->item_id);
		}

		# tady je workaround k chybe, kdy SimpleXmlElement ignoruje konstantu LIBXML_NOXMLDECL a ve vystupu pak je deklarace xml
		# Tady se to nehodi, protoze pretvarime kazdy node item zvlast, kde deklaraci nepotrebujeme.
		$dom = dom_import_simplexml($_xml);
		return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement)."\n";
	}
}

