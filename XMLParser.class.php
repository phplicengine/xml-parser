<?php
namespace XMLParser;
use Exception;
use InvalidArgumentException;
use SimpleXMLElement;

class XMLParser {

  private static $_defaultRootNode = 'root';

  private static $_defaultListNode = 'list';

  private static $_defaultItemNode = 'item';

  private static $_defaultEncoding = 'UTF-8';

  private static $_defaultVersion  = '1.0';

  private static $_defaultAttrTag  = 'attr:';

  public static function encode ($data, $root = null)
  {
    if ($data instanceof SimpleXMLElement) {
      return $data;
    }
    try {
      $data = self::_validateEncodeData($data);
      $version = self::$_defaultVersion;
      $encoding  = self::$_defaultEncoding;
      $xml_string = "<?xml version=\"{$version}\" encoding=\"{$encoding}\" ?>";
      $node = self::_formatName( is_null($root) ?
        self::$_defaultRootNode :
        $root);
      $value = ($is_array = is_array($data)) ? NULL : self::_formatValue($data);
      $xml_string .= "<$node>$value</$node>";
      $xml = new SimpleXMLElement($xml_string);
      if ($is_array) {
        $xml = self::_addChildren($xml,$data);
      }
    }
    catch (Exception $e) {
      trigger_error($e->getMessage(), E_USER_ERROR);
    }
    return isset($xml) ? $xml : null; // isset() essentially to make editor happy.
  }

  private function _validateEncodeData ($data)
  {
    if (is_object($data)) {
      throw new InvalidArgumentException(
        "Invalid data type supplied for XMLParser::encode"
      );
    }
    return $data;
  }

  private function _addChildren (SimpleXMLElement $element, $data)
  {
    foreach ($data as $key => $value) {
      $regex = '/^'.self::$_defaultAttrTag.'([a-z0-9\._-]*)/';
      $is_attr = preg_match($regex, $key, $attr);
      if ($is_attr) {
        if (is_array($value)) {
          foreach( $value as $k=>$v ) {
            $element->addAttribute(
              self::_formatName($k),
              self::_formatValue($v));
          }
        } else {
          $element->addAttribute(
            self::_formatName($attr[1]),
            self::_formatValue($value)
          );
        }
        continue;
      }
      $node = self::_formatName(
        is_numeric($key) ?
          (is_array($value) ?
            self::$_defaultListNode :
            self::$_defaultItemNode) : $key
      );
      if (is_array($value)) {
        $child = $element->addChild($node);
        self::_addChildren($child,$value);
        continue;
      }
      $element->addChild($node, $value);
    }
    return $element;
  }

  private static function _formatName ($string)
  {
    $p = [
      '/[^a-z0-9\._ -]/i' => '',
      '/(?=^[[0-9]\.\-\:^xml])/i' => self::$_defaultItemNode.'_',
      '/ /' => '_'
    ];
    $string = preg_replace(array_keys($p), array_values($p), $string);
    return strtolower($string);
  }

  private static function _formatValue ($string)
  {
    $string = is_null($string) ? 'NULL' : $string;
    return is_bool($string) ? self::_bool($string) : $string;
  }

  private static function _bool ($bool)
  {
    return $bool ? 'TRUE' : 'FALSE';
  }

  public static function decode ($xml)
  {
    if (!$xml instanceof SimpleXMLElement)
    {
      $xml = new SimpleXMLElement($xml);
    }
    return self::_toArray($xml);
  }

  private static function _toArray (SimpleXMLElement $element)
  {
    $array = [];
    $attributes = (array)$element->attributes();
    if (array_key_exists('@attributes',$attributes)){
      $array['attr:'] = $attributes['@attributes'];
    }
    foreach ($element->children() as $key => $child) {
      $value = (string)$child;
      $_children = self::_toArray($child);
      $_push = ($_hasChild = (count($_children)>0)) ? $_children : $value;
      if( $_hasChild && !empty($value) && $value !== '') {
        $_push[]=$value;
      }
      $array[$key] = $_push;
    }
    return $array;
  }
}