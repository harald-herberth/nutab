<?php

require_once 'JSON.php';

/**
Copyright (c) 2006 David Sklar

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/


class BadgerFish {
    public static function encode(DOMNode $node, $level = 0) {
        static $xpath;
        if (is_null($xpath)) {
            $xpath = new DOMXPath($node);
        }
        
        if ($node->childNodes) {
            $r = array();
            $text = '';
            foreach ($node->childNodes as $child) {
                $idx = $child->nodeName;
                if (! is_null($cr = self::encode($child, $level+1))) {
                    if (($child->nodeType == XML_TEXT_NODE)||($child->nodeType == XML_CDATA_SECTION_NODE)) {
                        $text .= $cr;
                    } else {
                        $r[$idx][] = $cr;
                    }
                }
            }
            
            // Reduce 1-element numeric arrays
            foreach ($r as $idx => $v) {
                if (is_array($v) && (count($v) == 1) && isset($v[0])) {
                    $r[$idx] = $v[0];
                }
            }
            
            // Any accumulated text that isn't just whitespace?
            if (strlen(trim($text))) { $r['$'] = $text; }

            // Attributes?
            if ($node->attributes && $node->attributes->length) {
                foreach ($node->attributes as $attr) {
                    $r['@'.$attr->nodeName] = $attr->value;
                }
            }
            
            // Namespaces?
            foreach ($xpath->query('namespace::*[name() != "xml"]', $node) as $ns) {
                if ($ns->localName == 'xmlns') {
                    $r['@xmlns']['$'] = $ns->namespaceURI;
                } else {
                    $r['@xmlns'][$ns->localName] = $ns->namespaceURI;
                }
            }
        }
        // No children -- just return text;
        else {
            if (($node->nodeType == XML_TEXT_NODE)||($node->nodeType == XML_CDATA_SECTION_NODE)) {
                return $node->textContent;
            }
        }
        if ($level == 0) {
            $json = new Services_Json();
            $xpath = null;
            return $json->encode($r);
        } else {
            return $r;
        }
    }
}

