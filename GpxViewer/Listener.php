<?php

class ABDS_GpxViewer_Listener
{

    /**
     * The twig environment
     *
     * @var \Twig_Environment
     */
    static $twig;

    /**
     * Have we already added the Google Maps JavaScript to the page?
     *
     * @var boolean
     */
    static $isMapLoaded;

    /**
     * Add the first .gpx file to the post as an embedded Google Map
     *
     * @param string $hookName The hook name
     * @param string $contents The post contents
     * @param array $hookParams The hook parameters
     * @param \XenForo_Template_Abstract $template The template
     */
    public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template) {
        if ($hookName == "message_content") {
            $numAttachments = $hookParams['message']['attach_count'];
            if ($numAttachments > 0) {

                // Are there any .gpx attachments to this post?
                $hasGpxAttachment = null;
                foreach ($hookParams['message']['attachments'] as $attachment) {
                    if (preg_match('/.gpx/i', $attachment['filename'])) {
                        $hasGpxAttachment = true;
                        break;
                    }
                }
                if (!$hasGpxAttachment) {
                    return;
                }

                // Load the visitor's information
                $visitor = \XenForo_Visitor::getInstance();

                // Load the plugin options
                $options = \XenForo_Application::get('options');

                // Inialize twig
                if (!static::$twig) {
                    require_once __DIR__ . '/../vendor/autoload.php';

                    \Twig_Autoloader::register();
                    $loader = new \Twig_Loader_Filesystem(__DIR__ . '/templates');
                    static::$twig = new \Twig_Environment($loader, array(
                        'debug' => $config['debug']
                    ));
                }

                // Get the path to the attachment
                $model = new \XenForo_Model_Attachment();
                $filePath = $model->getAttachmentDataFilePath($attachment);

                // Get the gpx xml
                if (!file_exists($filePath)) {
                    if ($config['debug']) {
                        echo "Attachment " . $attachment['attachment_id'] ." file does not exist. Skipping.";
                    }
                    return;
                }
                $gpxString = file_get_contents($filePath);
                if (!$gpxString) {
                    if ($config['debug']) {
                        echo "Attachment " . $attachment['attachment_id'] ." has no file data, or was not found. Skipping.";
                    }
                    return;
                }

                $htmlPrefix = null;
                $htmlPostfix = null;
                try {
                    // Allow SimpleXML to parse Garmin's odd <gpxx:foo> syntax by trimming off the "gpxx:" portion of the tag
                    $gpxString = str_replace('<gpxx:', '<', $gpxString);
                    $gpxString = str_replace('</gpxx:', '</', $gpxString);
                    $xml = new \SimpleXMLElement($gpxString);

                    // Extract the locations the xml
                    $locations = self::getLocationsFromXml($xml, $visitor);
                    if (empty($locations)) {
                        if ($config['debug']) {
                            echo "Attachment " . $attachment['attachment_id'] ." has a .gpx extension, but doesn't appear to be valid GPX, skipping.";
                        }
                        return;
                    }
                    
                    $htmlPrefix .= static::$twig->render('view.twig', array(
                        'attachment_id' => $attachment['attachment_id'],
                        'attachment_filename' => $attachment['filename'],
                        'locations' => $locations,
                        'initial_time' => $locations[0]['time'],
                        'button_colour' => $options->abds_gpxviewer_button_colour,
                        'google_maps_api_key' => $options->abds_gpxviewer_google_maps_api_key,
                        'marker_colour' => $options->abds_gpxviewer_marker_colour,
                        'marker_colour_selected' => $options->abds_gpxviewer_marker_colour_selected,
                        'is_map_loaded' => static::$isMapLoaded
                    ));

                    // Mark the map as having been loaded on this page
                    static::$isMapLoaded = true;
                } catch (Exception $e) {
                    $htmlPostfix .= "<br /><br /><small>(Bad XML in GPX file " . $attachment['filename'] . ", cannot display as map)</small>";
                }

                $contents = $htmlPrefix . $contents . $htmlPostfix;
            }
        }
    }

    /**
     * Convert the GPX tile to a human readable version
     *
     * @param string $dateTime The GPX time
     * @param string $visitorTimezone The visitor's timezone
     *
     * @return string
     */
    public static function gpxTimeToHumanReadable($dateTime, $visitorTimezone) {
        $gpxTimezone = new \DateTimeZone('GMT');
        $gpxTime = new \DateTime($dateTime, $gpxTimezone);
        $userTimezone = new \DateTimezone($visitorTimezone);
        $gpxTime->setTimezone($userTimezone);

        return $gpxTime->format('M j, Y g:i:s a T');
    }

    /**
     * Extract locations from the XML
     *
     * @param \SimpleXMLElement $xml The XML
     * @param \XenForo_Visitor $visitor The visitor
     *
     * @return array The array of location information
     */
    public static function getLocationsFromXml($xml, \XenForo_Visitor $visitor) {
        $parsedLocations = null;
        $locations = array();
        if ($xml->attributes()->lat && $xml->attributes()->lon) {
            // Round the lat/lng down to 7 decimal points, which still gives us ~5 ft precision,
            // but hopefully reduces the amount of almost identical points
            $latitude = round((float) $xml->attributes()->lat, 7);
            $longitude = round((float) $xml->attributes()->lon, 7);

            // Don't add the same lat/lng to the locations array twice
            $id = md5($latitude . $longitude);
            if (!isset($parsedLocations[$id])) {
                $time = null;
                if ($xml->time) {
                    $time = self::gpxTimeToHumanReadable((string) $xml->time, $visitor->timezone);
                }

                $locations[] = array(
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'time' => $time
                );

                $parsedLocations[$id] = true;
            }
        }

        if ($xml->count() > 0) {
            foreach ($xml->children() as $child) {
                $locations = array_merge($locations, self::getLocationsFromXml($child, $visitor));
            }
        }

        return $locations;
    }
}
