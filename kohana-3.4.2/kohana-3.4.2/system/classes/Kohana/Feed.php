<?php

namespace Kohana\Helpers;

use Kohana_Exception;
use SimpleXMLElement;
use DateTime;

/**
 * RSS and Atom feed helper.
 *
 * @package    Kohana
 * @category   Helpers
 */
class Kohana_Feed
{
    /**
     * Parses a remote feed into an array.
     *
     * @param   string  $feed   remote feed URL
     * @param   int     $limit  item limit to fetch
     * @return  array
     * @throws  Kohana_Exception
     */
    public static function parse(string $feed, int $limit = 0): array
    {
        // Check if SimpleXML is installed
        if (!function_exists('simplexml_load_string')) {
            throw new Kohana_Exception('SimpleXML must be installed!');
        }

        // Disable error reporting while opening the feed
        $error_level = error_reporting(0);

        // Load the feed contents
        if (filter_var($feed, FILTER_VALIDATE_URL)) {
            // Use native Request client to get remote contents
            $response = Request::factory($feed)->execute();
            $feed = $response->body();
        } elseif (is_file($feed)) {
            // Get file contents
            $feed = file_get_contents($feed);
        }

        // Load the feed
        $feedXml = simplexml_load_string($feed, SimpleXMLElement::class, LIBXML_NOCDATA);

        // Restore error reporting
        error_reporting($error_level);

        // Feed could not be loaded
        if ($feedXml === false) {
            return [];
        }

        $namespaces = $feedXml->getNamespaces(true);

        // Detect the feed type. RSS 1.0/2.0 and Atom 1.0 are supported.
        $itemsXml = isset($feedXml->channel) ? $feedXml->xpath('//item') : $feedXml->entry;

        $items = [];
        foreach ($itemsXml as $i => $itemXml) {
            if ($limit > 0 && $i === $limit) {
                break;
            }

            $itemFields = (array) $itemXml;

            // Get namespaced tags
            foreach ($namespaces as $ns) {
                $itemFields += (array) $itemXml->children($ns);
            }

            $items[] = $itemFields;
        }

        return $items;
    }

    /**
     * Creates a feed from the given parameters.
     *
     * @param   array   $info       feed information
     * @param   array   $items      items to add to the feed
     * @param   string  $encoding   define which encoding to use
     * @return  string
     * @throws  Kohana_Exception
     */
    public static function create(array $info, array $items, string $encoding = 'UTF-8'): string
    {
        $info += ['title' => 'Generated Feed', 'link' => '', 'generator' => 'KohanaPHP'];

        $feed = '<?xml version="1.0" encoding="' . $encoding . '"?><rss version="2.0"><channel></channel></rss>';
        $feedXml = simplexml_load_string($feed);

        foreach ($info as $name => $value) {
            if ($name === 'image') {
                // Create an image element
                $image = $feedXml->channel->addChild('image');

                if (!isset($value['link'], $value['url'], $value['title'])) {
                    throw new Kohana_Exception('Feed images require a link, url, and title');
                }

                if (!filter_var($value['link'], FILTER_VALIDATE_URL)) {
                    // Convert URIs to URLs
                    $value['link'] = URL::site($value['link'], 'http');
                }

                if (!filter_var($value['url'], FILTER_VALIDATE_URL)) {
                    // Convert URIs to URLs
                    $value['url'] = URL::site($value['url'], 'http');
                }

                // Create the image elements
                $image->addChild('link', $value['link']);
                $image->addChild('url', $value['url']);
                $image->addChild('title', $value['title']);
            } else {
                if (($name === 'pubDate' || $name === 'lastBuildDate') && (is_int($value) || ctype_digit($value))) {
                    // Convert timestamps to RFC 822 formatted dates
                    $date = new DateTime('@' . $value);
                    $value = $date->format(DateTime::RFC822);
                } elseif (($name === 'link' || $name === 'docs') && !filter_var($value, FILTER_VALIDATE_URL)) {
                    // Convert URIs to URLs
                    $value = URL::site($value, 'http');
                }

                // Add the info to the channel
                $feedXml->channel->addChild($name, $value);
            }
        }

        foreach ($items as $item) {
            // Add the item to the channel
            $row = $feedXml->channel->addChild('item');

            foreach ($item as $name => $value) {
                if ($name === 'pubDate' && (is_int($value) || ctype_digit($value))) {
                    $date = new DateTime('@' . $value);
                    $value = $date->format(DateTime::RFC822);
                } elseif (($name === 'link' || $name === 'guid') && !filter_var($value, FILTER_VALIDATE_URL)) {
                    // Convert URIs to URLs
                    $value = URL::site($value, 'http');
                }

                // Add the info to the row
                $row->addChild($name, htmlspecialchars($value, ENT_QUOTES, $encoding));
            }
        }

        if (function_exists('dom_import_simplexml')) {
            // Convert the feed object to a DOM object
            $dom = dom_import_simplexml($feedXml)->ownerDocument;

            // DOM generates more readable XML
            $dom->formatOutput = true;

            // Export the document as XML
            return $dom->saveXML();
        } else {
            // Export the document as XML
            return $feedXml->asXML();
        }
    }
}
