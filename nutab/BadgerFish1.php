<?php
/**
 * BadgerFish Transformations and Addressing
 *
 * PHP version 5.3
 *
 * @category Imperium
 * @package  Imperium\BadgerFish
 * @author   Joby Walker <joby@imperium.org>
 * @license  http://unlicense.org/ UNLICENSE
 * @link     https://github.com/jobywalker/Imperium-BadgerFish
 */

namespace Imperium;

/**
 * Class of static methods to perform BadgerFish transformations and work with the datastructure
 *
 * @category Imperium
 * @package  Imperium\BadgerFish
 * @author   Joby Walker <joby@imperium.org>
 * @license  http://unlicense.org/ UNLICENSE
 * @link     https://github.com/jobywalker/Imperium-BadgerFish
 */
class BadgerFish
{
    /**
     * Convert an XML String into JSON serialization
     *
     * @param string $xml XML String
     * @return string JSON Serialization
     */
    public static function xmlToJson($xml)
    {
        return \json_encode(self::XmlToPhp($xml));
    }

    /**
     * Convert an XML String into a PHP datastructure
     *
     * @param string $xml XML String
     * @return mixed PHP datastructure
     */
    public static function xmlToPhp($xml)
    {
        $sxe = \simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR);
        if (!($sxe instanceof \SimpleXMLElement)) {
            throw new \Exception('XML is not parsable');
        }
        return self::SimpleXmlToPhp($sxe);
    }
    
    /**
     * Convert a SimpleXML datastructure into a JSON serialization
     *
     * @param \SimpleXMLElement $sxe SimpleXMLElemnent to transform
     * @return string JSON Serialization
     */
    public static function simpleXmlToJson(\SimpleXMLElement $sxe)
    {
        return \json_encode(self::SimpleXmlToPhp($sxe));
    }

    /**
     * Convert a SimpleXML datastructure into a PHP datastructure
     *
     * @param \SimpleXMLElement $sxe SimpleXMLElemnent to transform
     * @return mixed PHP datastructure
     */
    public static function simpleXmlToPhp(\SimpleXMLElement $sxe)
    {
        $name = $sxe->getName();
        $wrap = array($name=>$sxe);
        return self::badgerfy($wrap);
    }

    /**
     * Perform BadgerFish transform on the supplied parameter
     *
     * @param mixed $data Transform this
     * @return mixed
     */
    public static function badgerfy($data)
    {
        $return = array();
        if ($data instanceof \SimpleXMLElement) {
            $attrs = $data->attributes();
            $children = $data->children();
            if (count($attrs)==0 && count($children)==0) {
                return self::badgerfy((string)$data);
            }
            if (count($attrs)) {
                foreach ($attrs as $aname => $avalue) {
                    $return["@$aname"] = self::badgerfy($avalue);
                }
            }
            if (count($children)) {
                $counter = array();
                foreach ($children as $name => $child) {
                    if (!isset($counter[$name])) {
                        $counter[$name] = 1;
                    } else {
                        $counter[$name]++;
                    }
                    if ($counter[$name]==1) {
                        $return[$name] = self::badgerfy($child);
                    } elseif ($counter[$name]==2) {
                        $return[$name] = array($return[$name], self::badgerfy($child));
                    } else {
                        $return[$name][] = self::badgerfy($child);
                    }
                }
            } else {
                $return['$'] = self::badgerfy((string)$data);
            }
        } elseif (\is_object($data)) {
            foreach ((array)$data as $key => $value) {
                $return[$key] = self::badgerfy($value);
            }
        } elseif (\is_array($data)) {
            foreach ($data as $key => $value) {
                $return[$key] = self::badgerfy($value);
            }
        } else {
            if (strtolower($data) == 'true') {
                return true;
            } elseif (strtolower($data) == 'false') {
                return false;
            } elseif (is_numeric($data)) {
                return floatval($data);
            } else {
                return $data;
            }
        }
        return $return;
    }

    /**
     * Convert JSON serialization into XML
     *
     * @param string $json JSON string to transform
     * @return string
     */
    public static function jsonToXml($json)
    {
        return self::phpToXml(\json_decode($json, false));
    }

    /**
     * Convert JSON serialized content to PHP
     * 
     * @param string $json JSON Encoded content
     * @return mixed PHP datastructure
     */
    public static function jsonToPhp($json)
    {
        return \json_decode($json, false);
    }

    /**
     * Convert a PHP datastructure into a Valid XML document
     *
     * @param mixed $php PHP to transform
     * @return string
     */
    public static function phpToXml($php)
    {
        return self::phpToSXE($php)->asXML();
    }

    /**
     * Convert a PHP datastructure into a SimpleXMLElement structure
     *
     * @param mixed $php PHP to transform
     * @return SimpleXMLElement
     */
    public static function phpToSXE($php)
    {
        if (\is_object($php)) {
            $php = (array)$php;
        }
        if (!\is_array($php) || count($php)!==1) {
            throw new \Exception('Data is not properly formatted for generating XML root');
        }
        list($root) = \array_keys($php);
        $content    = \is_object($php[$root]) ? (array)$php[$root]: $php[$root];
        $rootXml    = isset($content['$']) ? "<$root>".$content['$']."</$root>" : "<$root/>";
        $sxe = \simplexml_load_string(
            $rootXml,
            'SimpleXMLElement', LIBXML_NOCDATA|LIBXML_NOERROR
        );
        if (!($sxe instanceof \SimpleXMLElement)) {
            throw new \Exception('Can not be created as XML');
        }
        self::debadger($php[$root], $sxe);
        return $sxe;
    }

    /**
     * Recursively transform PHP datastructure into a SimpleXMLElement
     *
     * @param mixed             $data  Data to manipulate
     * @param \SimpleXMLElement $sxe   SimpleXMLElement to add elements/attributes to
     * @param mixed             $field Field name
     * @return void
     */
    public static function debadger($data, \SimpleXMLElement $sxe, $field = null)
    {
        if (\is_array($data) && count($data)===0) {
            return;
        } elseif (\is_object($data)
            || (\is_array($data) && \array_keys($data) !== range(0, count($data)-1))
        ) {
            $data = (array)$data;
            if ($field) {
                if (isset($data['$'])) {
                    $child = self::addString($data['$'], $sxe, $field);
                } else {
                    $child = self::addString(null, $sxe, $field);
                }
            } else {
                $child = $sxe;
            }
            foreach ((array)$data as $key => $value) {
                self::debadger($value, $child, $key);
            }
        } elseif ($field == null) {
            throw new \Exception('Invalid processing');
        } elseif (\is_array($data)) {
            foreach ($data as $value) {
                self::debadger($value, $sxe, $field);
            }
        } elseif ($data === true) {
            self::addString('true', $sxe, $field);
        } elseif ($data === false) {
            self::addString('false', $sxe, $field);
        } elseif ($data) {
            self::addString("$data", $sxe, $field);
        } else {
            self::addString('', $sxe, $field);
        }
    }

    /**
     * Add the string value as a child or attribute of the SimpleXMLElement
     *
     * @param string            $string Value to add
     * @param \SimpleXMLElement $sxe    SimpleXMLElement to add elements/attributes to
     * @param string            $field  Field Name
     * @return \SimpleXMLElement
     */
    public static function addString($string, \SimpleXMLElement $sxe, $field)
    {
        if ($field == '$') {
            // do nothing
        } elseif (preg_match('/^@/', $field)) {
            $sxe->addAttribute(\mb_substr($field, 1), $string);
        } else {
            if ($string) {
                return $sxe->addChild($field, preg_replace('/&(?!amp;)/u', '&amp;', $string));
            } else {
                return $sxe->addChild($field);
            }
        }
    }


    /**
     * Retrieve the value at the specified path
     *
     * @param array  $badger BadgerFish data structure
     * @param string $path   Path in . sparated notation
     * @return mixed
     */
    public static function getValue($badger, $path)
    {
        $path = mb_split('\.', $path);
        $max  = count($path)-1;
        $value = $badger;
        for ($x=0; $x<=$max; $x++) {
            if (\is_object($value)) {
                $value = (array)$value;
            } elseif (!\is_array($value)) {
                return null;
            }
            $value = isset($value[$path[$x]]) ? $value[$path[$x]] : null;
        }
        if (\is_array($value)||\is_object($value)) {
            $test = \is_object($value) ? (array)$value : $value;
            if (isset($test['$'])) {
                return $test['$'];
            }
        }
        return $value;
    }

    /**
     * Retrieve an array (not assoc-array) at the specified path
     * 
     * @param array  $badger BadgerFish data structure
     * @param string $path   Path in . sparated notation
     * @return array
     */
    public static function getArray($badger, $path)
    {
        $value = self::getValue($badger, $path);
        if ($value === null || $value === array()) {
            return array();
        } elseif (\is_array($value) && \array_keys($value) === range(0, count($value)-1)) {
            return $value;
        }
        return array($value);
    }
}