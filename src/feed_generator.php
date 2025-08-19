<?php
namespace ProductFeedGenerator;

class FeedGenerator {

	function __construct($reader_or_options, $options=null) {
		if (is_array($reader_or_options) && is_null($options)) {
			$options = $reader_or_options;
			$reader = $reader_or_options = null;
		} else {
			$reader = $reader_or_options;
			$reader_or_options = null;
		}

		$this->reader = $reader;

		$options += [
			"full_feed" => !DEVELOPMENT,
			"logger" => new \logger(),
			"output_format" => "xml",

			# output options
			"xml_item_element_name" => "item",
			"feed_begin" => null,
			"feed_end" => null,
			"fixed_values" => [],

			# reader options
			"hostname" => null,
		];

		$this->logger = $options["logger"];

		$this->options = $options;

		if (is_null($this->reader)) {
			$_reader_options = [
			] + $options;
			$this->reader = new \ProductFeedGenerator\Reader\Atk14EshopReader($_reader_options);
		}
		$this->reader->setGeneratorOptions($this->options);
	}

	function exportTo($output_filename, $options=[]) {

		if (php_sapi_name()=="cli" && isset($_SERVER["TERM"])) {
			print("generating xml feed {$output_filename}\n\n");
		}
		$this->logger->info("generating xml feed {$output_filename}");

		if ($this->_gen_feed($output_filename)) {
			$this->logger->info("xml product feed {$output_filename} successfully generated");
		} else {
			$this->logger->info("xml product feed {$output_filename} not generated");
		}
	}

	protected function _gen_feed($output_filename) {
		$_object_to_ar_options = [];

		$output_format = $this->getOutputFormat();
		$filename_tmp = \Files::GetTempFilename();

		if ($output_format == "xml") {
			$xml_head = $this->generateXmlBegin();
			\Files::WriteToFile($filename_tmp, trim("$xml_head")."\n");
		}

		$count = sizeof($this->reader->getObjectIds(["limit" => null]));

		$offset = 0; $limit = 100;

		while (
			$objects = $this->reader->getObjects([
				"offset" => $offset,
			])
		) {

			$xml_out = "";
			$write_header = true;
			$fixed_values = $this->options["fixed_values"];

			foreach($objects as $_object) {
				$item_out = "";

				# ziskani hodnot v poli v univerzalni podobe
				# jsou tam vsechny hodnoty, ktere se mohou hodit pro ruzne typy feedu
				$itemAr = $this->reader->objectToArray($_object,$_object_to_ar_options);

				# prevedeme do podoby pro dany typ sluzby (feedu),
				# vrati se jen nektere hodnoty se specifickymi nazvy klicu
				$itemAr = $this->transformForService($itemAr);

				# pridame fixni hodnoty, ktere neni treba zjistovat z db produktu, nebo jsou stejne u vsech items
				# pripadne chceme posila nejakou hodnotu specifickou pro dany feed
				# @todo bude stacit, kdyz je tam nacpeme skrz $options
				array_walk($itemAr, function(&$item) use ($fixed_values) {
					$item = $fixed_values + $item;
					return $item;
				});

				$null_keys = array_keys(array_filter($fixed_values, "is_null"));
				# keys in fixed_values containing value null will be removed from output
				array_walk($itemAr, function(&$item) use ($null_keys) {
					foreach($null_keys as $k) {
						unset($item[$k]);
					}
					return $item;
				});

				array_walk($itemAr, function(&$item) {
					$item = $this->afterFilter($item);
				});

				array_walk($itemAr, "ksort");

				switch($this->options["output_format"]) {
				case "csv":
					foreach($itemAr as $ar) {
						foreach($ar as &$_a) {
							if (is_array($_a)) {
								$_a = join("\n", $_a);
							}
						}
						$item_out .= $this->_array_to_csv($ar, $write_header);
						$write_header = false;
					}
					break;
				default:
					$xml_item_element_name = $this->options["xml_item_element_name"];
					$item_out = $this->_array_to_xml($itemAr, $xml_item_element_name);
					break;
				}
				$xml_out .= $item_out;
			}

			\Files::AppendToFile($filename_tmp,$xml_out);

			if (php_sapi_name()=="cli") {
				isset($_SERVER["TERM"]) && print(sprintf("processed %d records of %d\n", $offset+sizeof($objects), $count));
			}
			$offset += $limit;
			if ($this->options["full_feed"]===false) {
				print("\nDEVELOPMENT mode => we have enough => break\n\n");
				break;
			}
		}
		if ($output_format == "xml") {
			$_feed_end = $this->generateXmlEnd();
			$_feed_end && \Files::AppendToFile($filename_tmp, "\n".$_feed_end);
		}

		\Files::MoveFile($filename_tmp,$output_filename, $error, $error_string);
		if ($error) {
			throw new \Exception($error_string);
		}
		return true;
	}

	function generateXmlEnd() {
		return $this->options["feed_end"];
	}

	function generateXmlBegin() {
		$_prolog = "";
		if ($this->getOutputFormat()=="xml") {
			$_prolog = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		}

		$_feed_begin = "";
		$_feed_title = "";
		$_eshop_url = "";

		if (isset($this->options["feed_title"])) {
			$_feed_title = $this->_array_to_xml(["title" => $this->options["feed_title"]]);
		}
		if (isset($this->options["eshop_url"])) {
			$_eshop_url = sprintf("<link rel=\"self\" href=\"https://%s\" />\n", $this->options["eshop_url"]);
		}

		$_feed_begin = $this->options["feed_begin"];
		$_begin = [
			$_prolog,
			$_feed_begin,
			$_feed_title,
			$_eshop_url,
			"\n",
		];
		return join("\n", $_begin);

		return "{$_prolog}\n{$_feed_begin}\n{$_feed_title}\n{$_eshop_url}\n";
	}

	function getOutputFormat() {
		return $this->options["output_format"];
	}

	function transformForService($object_ar=[]) {
		$transformMap = $this->getAttributesMap();

		$out = [];
		foreach($object_ar as $_prodAr) {
			$_out = [];
			foreach($transformMap as $target_field => $source_field) {
				if (isset($_prodAr[$source_field])) {
					$_out[$target_field] = $_prodAr[$source_field];
				}
			}
			$out[] = $_out;
		}

		return $out;
	}

	function _array_to_csv($ar, $write_header=false) {
		ob_start();
		$fp = fopen('php://output', 'w');

		if($write_header) {
			fputcsv($fp, array_keys($ar), ";", "\"");
		}
		fputcsv($fp, $ar, ";", "\"");
		fclose($fp);

		$csv = ob_get_clean();
		return $csv;
	}

	function _array_to_xml($ar,$root_element = "",$_prev_root_element=""){
		$out = array();
		if($root_element && ($root_element==$_prev_root_element)){ $out[] = "<$root_element>"; }
		foreach($ar as $k => $v){
			if(is_numeric($k)){
				$k = "item";
				if($root_element){
					// "editions" -> "edition"
					$k = new \String4($root_element);
					$k = $k->singularize();
				}
			}
			if(is_array($v)){
				if ($_out = $this->_array_to_xml($v,$k,$root_element)) {
				$out[] = $_out;
				}
			}else{
				if(is_bool($v)){
					$v = $v ? "true" : "false";
				}
				$out[] = "<$k>".\XMole::ToXML($v)."</$k>";
			}
		}
		if($root_element && ($root_element==$_prev_root_element)){ $out[] = "</$root_element>"; }
		return join("\n",$out);
	}

	function afterFilter($values) {
		return $values;
	}
}
